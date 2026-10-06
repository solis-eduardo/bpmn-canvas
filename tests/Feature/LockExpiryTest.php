<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LockExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function heldDiagram(User $holder): Diagram
    {
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug)->assertOk();

        return $diagram;
    }

    private function draft(string $label): string
    {
        return preg_replace('/ name="[^"]*"/', ' name="'.$label.'"', Diagram::emptyBpmn(), 1);
    }

    public function test_a_lock_without_heartbeat_for_the_ttl_is_released(): void
    {
        $diagram = $this->heldDiagram(User::factory()->create());

        $this->travel(5)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();

        $this->assertNull($diagram->fresh()->locked_by);
    }

    public function test_a_lock_still_within_the_ttl_is_kept(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);

        $this->travel(4)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_a_heartbeat_postpones_expiry(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);

        $this->travel(4)->minutes();
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->travel(4)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_expiry_saves_the_unsaved_draft_as_a_provisional_version(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $versions = app(VersionRepository::class);
        $before = count($versions->list($diagram));

        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->draft('Unsaved work')])->assertOk();
        $this->travel(5)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();

        $diagram = $diagram->fresh();
        $this->assertNull($diagram->locked_by);
        $this->assertNull($diagram->lock_draft);

        $list = $versions->list($diagram);
        $this->assertCount($before + 1, $list);
        $this->assertSame(__('messages.provisional_version'), $list[0]['name']);
        $this->assertSame($holder->name, $list[0]['author']);
        $this->assertStringContainsString('Unsaved work', Storage::disk('diagrams')->get($diagram->disk_path));
    }

    public function test_expiry_without_a_draft_creates_no_version(): void
    {
        $diagram = $this->heldDiagram(User::factory()->create());
        $before = count(app(VersionRepository::class)->list($diagram));

        $this->travel(5)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();

        $this->assertCount($before, app(VersionRepository::class)->list($diagram));
    }

    public function test_saving_the_diagram_discards_the_draft(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->draft('Draft')])->assertOk();

        $this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/content', ['content' => $this->draft('Saved')])->assertOk();

        $this->assertNull($diagram->fresh()->lock_draft);
    }

    public function test_expiry_never_transfers_the_lock_to_a_requester(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $diagram->forceFill(['lock_requested_by' => $requester->id, 'lock_requested_at' => now()])->save();

        $this->travel(5)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();

        $diagram = $diagram->fresh();
        $this->assertNull($diagram->locked_by);
        $this->assertNull($diagram->lock_requested_by);
    }

    public function test_an_administrator_without_edit_rights_gets_the_viewer_not_the_editor(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->heldDiagram($holder);

        $this->actingAs($admin)->get('/diagrams/'.$diagram->slug)
            ->assertOk()
            ->assertViewIs('shares.show')
            ->assertDontSee('id="btn-save"', false)
            ->assertSee(__('messages.read_only_diagram_notice'));

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_saving_a_diagram_locked_by_someone_else_explains_who_holds_it(): void
    {
        $holder = User::factory()->create(['name' => 'Carla Holder']);
        $other = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $other->id, 'status' => Collaborator::STATUS_APPROVED]);

        $this->actingAs($other)->putJson('/diagrams/'.$diagram->slug.'/content', ['content' => $this->draft('X')])
            ->assertStatus(423)
            ->assertJsonPath('message', __('messages.error_diagram_locked_by', ['name' => 'Carla Holder']));
    }

    public function test_saving_without_edit_rights_explains_the_missing_permission(): void
    {
        $diagram = $this->heldDiagram(User::factory()->create());
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/diagrams/'.$diagram->slug.'/content', ['content' => $this->draft('X')])
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.error_cannot_edit_diagram'));
    }

    public function test_a_heartbeat_after_losing_the_lock_says_so(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->travel(5)->minutes();
        $this->artisan('locks:expire');

        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.error_lock_lost'));
    }

    public function test_json_not_found_errors_are_localized(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/diagrams/does-not-exist/content')
            ->assertNotFound()
            ->assertJsonPath('message', __('messages.error_not_found'));
    }

    /**
     * A draft that doesn't validate must not cost the holder their Lock:
     * renewing is the Heartbeat's job, carrying the draft is a bonus.
     */
    public function test_an_invalid_draft_is_dropped_without_failing_the_heartbeat(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);

        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', [
            'draft' => 'not bpmn at all',
        ])->assertOk()->assertJsonPath('heartbeat', true);

        $this->assertNull($diagram->fresh()->lock_draft);
        $this->assertSame($holder->id, (int) $diagram->fresh()->locked_by);
    }

    /** Restoring a Version settles the content, so the draft is stale. */
    public function test_restoring_a_version_clears_the_lock_draft(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $versions = app(VersionRepository::class);
        $first = $versions->list($diagram)[0]['id'];

        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', [
            'draft' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">draft</bpmn:definitions>',
        ])->assertOk();
        $this->assertNotNull($diagram->fresh()->lock_draft);

        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/versions/'.$first.'/restore')->assertOk();

        $this->assertNull($diagram->fresh()->lock_draft);
    }
}
