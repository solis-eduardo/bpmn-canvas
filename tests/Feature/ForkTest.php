<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        config(['app.locale' => 'pt_BR']);
    }

    public function test_the_editor_offers_a_copy_of_an_unlocked_diagram(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        Storage::disk('diagrams')->put($diagram->disk_path, '<bpmn/>');

        $response = $this->actingAs($owner)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertSee(route('diagrams.fork', $diagram->slug), false);
    }

    public function test_forking_an_unlocked_diagram_creates_a_copy_titled_with_the_default_language_prefix_not_the_users(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create(['title' => 'Compras']);
        Storage::disk('diagrams')->put($diagram->disk_path, '<bpmn>x</bpmn>');
        $this->assertNull($diagram->locked_by);

        $response = $this->actingAs($owner)
            ->withSession(['locale' => 'en'])
            ->post('/diagrams/'.$diagram->slug.'/fork');

        $fork = Diagram::where('forked_from_id', $diagram->id)->firstOrFail();
        $response->assertRedirect(route('diagrams.show', $fork->slug));
        $this->assertSame('Cópia de Compras', $fork->title);
        $this->assertSame('copia-de-compras', $fork->slug);
        $this->assertSame($owner->id, $fork->owner_id);
        $this->assertSame('<bpmn>x</bpmn>', Storage::disk('diagrams')->get($fork->disk_path));
    }

    public function test_forking_into_a_workspace_the_user_is_not_a_member_of_is_refused(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($owner)->post('/diagrams/'.$diagram->slug.'/fork', [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('diagrams', ['forked_from_id' => $diagram->id]);
    }

    public function test_a_user_who_cannot_open_the_diagram_cannot_fork_it(): void
    {
        $diagram = Diagram::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post('/diagrams/'.$diagram->slug.'/fork');

        $response->assertForbidden();
        $this->assertDatabaseMissing('diagrams', ['forked_from_id' => $diagram->id]);
    }
}
