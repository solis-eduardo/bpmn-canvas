<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Optimistic concurrency on saves (issue #243): a save names the Version its
 * content was loaded from, and is refused when a newer Version exists.
 */
class DiagramSaveConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function xml(string $label): string
    {
        return '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">'.$label.'</bpmn:definitions>';
    }

    private function contentOf(User $user, Diagram $diagram): array
    {
        return $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/content')->assertOk()->json();
    }

    private function save(User $user, Diagram $diagram, string $label, array $extra = [])
    {
        return $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => $this->xml($label),
            ...$extra,
        ]);
    }

    public function test_the_content_names_the_version_it_is_at(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $current = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions.0.id');

        $this->assertNotNull($current);
        $this->assertSame($current, $this->contentOf($user, $diagram)['version']);
    }

    public function test_a_save_over_the_current_version_proceeds_and_names_the_new_one(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $base = $this->contentOf($user, $diagram)['version'];

        $response = $this->save($user, $diagram, 'first', ['base_version' => $base]);

        $response->assertOk();
        $this->assertNotSame($base, $response->json('version'));
        $this->assertSame($response->json('version'), $this->contentOf($user, $diagram)['version']);
    }

    public function test_a_save_over_an_outdated_version_is_refused_and_writes_nothing(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $base = $this->contentOf($user, $diagram)['version'];

        // The same User saves from another tab first.
        $this->save($user, $diagram, 'other tab')->assertOk();
        $newer = $this->contentOf($user, $diagram)['version'];

        $response = $this->save($user, $diagram, 'stale tab', ['base_version' => $base]);

        $response->assertStatus(409)
            ->assertJsonPath('current_version', $newer)
            ->assertJsonStructure(['message']);

        $after = $this->contentOf($user, $diagram);
        $this->assertSame($this->xml('other tab'), $after['content']);
        $this->assertSame($newer, $after['version']);
    }

    public function test_a_restore_makes_an_open_copy_outdated(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $original = $this->contentOf($user, $diagram)['version'];

        $this->save($user, $diagram, 'edited')->assertOk();

        $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/versions/'.$original.'/restore')->assertOk();

        $this->save($user, $diagram, 'from the open copy', ['base_version' => $original])
            ->assertStatus(409);
    }

    public function test_a_save_with_the_base_of_an_unchanged_content_is_still_accepted(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $base = $this->contentOf($user, $diagram)['version'];

        // Identical content records no new Version, so the base stays current.
        $response = $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => $this->contentOf($user, $diagram)['content'],
            'base_version' => $base,
        ]);

        $response->assertOk()->assertJsonPath('version', $base);
    }

    public function test_a_save_without_a_base_is_unconditional(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->save($user, $diagram, 'first')->assertOk();
        $this->save($user, $diagram, 'second')->assertOk();

        $this->assertSame($this->xml('second'), $this->contentOf($user, $diagram)['content']);
    }

    public function test_a_null_base_is_refused_once_a_version_exists(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->save($user, $diagram, 'first', ['base_version' => null])->assertStatus(409);
    }
}
