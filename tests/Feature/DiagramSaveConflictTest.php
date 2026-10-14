<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
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

    public function test_reading_the_content_does_not_describe_the_whole_history(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $expected = app(VersionRepository::class)->currentVersionId($diagram);

        // list() describes every Version (a `git log` on the default adapter):
        // a read only needs the id of the newest one.
        $repository = Mockery::mock(app(VersionRepository::class))->makePartial();
        $repository->shouldNotReceive('list');
        $this->app->instance(VersionRepository::class, $repository);

        $this->assertSame($expected, $this->contentOf($user, $diagram)['version']);
    }

    private function collaborator(Diagram $diagram, User $user): User
    {
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $user->id, 'status' => Collaborator::STATUS_APPROVED]);

        return $user;
    }

    private function draft(string $label): string
    {
        return preg_replace('/ name="[^"]*"/', ' name="'.$label.'"', Diagram::emptyBpmn(), 1);
    }

    /**
     * The holder's Lock expires with unsaved work in their open Editor, which
     * the server keeps as a provisional Version in their name.
     */
    private function expireLockKeepingDraft(User $holder, Diagram $diagram): void
    {
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->draft('Unsaved work')])->assertOk();
        $this->travel(5)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();
        $this->assertNull($diagram->fresh()->locked_by);
    }

    public function test_the_holders_own_draft_kept_by_an_expired_lock_is_not_a_conflict(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug)->assertOk();
        $base = $this->contentOf($holder, $diagram)['version'];

        $this->expireLockKeepingDraft($holder, $diagram);
        $this->assertNotSame($base, app(VersionRepository::class)->currentVersionId($diagram));

        // The same tab saves again with the Version it loaded.
        $response = $this->save($holder, $diagram, 'continued', ['base_version' => $base]);

        $response->assertOk();
        $this->assertSame($this->xml('continued'), $this->contentOf($holder, $diagram)['content']);
    }

    public function test_a_draft_kept_for_someone_else_does_not_excuse_a_stale_save(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $other = $this->collaborator($diagram, User::factory()->create());
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug)->assertOk();
        $base = $this->contentOf($holder, $diagram)['version'];
        $this->expireLockKeepingDraft($holder, $diagram);

        // The kept Version is the holder's, so for another User it is a conflict.
        $this->save($other, $diagram, 'stale', ['base_version' => $base])->assertStatus(409);
    }

    public function test_a_newer_save_after_the_kept_draft_is_a_conflict_even_for_the_holder(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $other = $this->collaborator($diagram, User::factory()->create());
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug)->assertOk();
        $base = $this->contentOf($holder, $diagram)['version'];
        $this->expireLockKeepingDraft($holder, $diagram);

        // Another User saves on top of the holder's kept draft, then the Lock is free again.
        $this->actingAs($other)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->save($other, $diagram, 'by someone else')->assertOk();
        $this->actingAs($other)->deleteJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->save($holder, $diagram, 'stale', ['base_version' => $base])->assertStatus(409);
    }
}
