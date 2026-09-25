<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditingLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    /**
     * The Lock only matters between users who already have edit permission
     * (Author or approved Collaborator, see ticket 06) — a User without that
     * permission is blocked by authorization before the Lock even comes
     * into play.
     */
    private function approveCollaborator(Diagram $diagram, User $user): void
    {
        DiagramCollaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);
    }

    public function test_opening_the_editor_without_an_active_lock_acquires_it_for_the_current_user(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $this->assertSame($user->id, $diagram->fresh()->locked_by);
        $this->assertNotNull($diagram->fresh()->locked_at);
        $this->assertNotNull($diagram->fresh()->last_heartbeat_at);
    }

    public function test_a_second_user_is_blocked_from_opening_an_actively_locked_diagram_and_sees_who_holds_the_lock(): void
    {
        $holder = User::factory()->create(['name' => 'Ana Author']);
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);

        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $response = $this->actingAs($other)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertSee('Ana Author');
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_the_listing_indicates_which_diagrams_are_actively_locked_and_by_whom(): void
    {
        $holder = User::factory()->create(['name' => 'Bruno Blocker']);
        $viewer = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $response = $this->actingAs($viewer)->get('/diagrams');

        $response->assertOk();
        $response->assertSee('being edited by Bruno Blocker');
    }

    public function test_the_listing_does_not_indicate_a_lock_that_expired_due_to_inactivity(): void
    {
        $holder = User::factory()->create(['name' => 'Carla Tired']);
        $viewer = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $this->travel(91)->seconds();

        $response = $this->actingAs($viewer)->get('/diagrams');

        $response->assertOk();
        $response->assertDontSee('being edited by');
    }

    public function test_explicitly_releasing_the_lock_allows_another_user_to_open_the_editor(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $this->actingAs($holder)->deleteJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->assertNull($diagram->fresh()->locked_by);

        $response = $this->actingAs($other)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertViewIs('diagrams.show');
        $this->assertSame($other->id, $diagram->fresh()->locked_by);
    }

    public function test_the_lock_is_released_after_a_period_of_inactivity_without_heartbeat(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $this->travel(91)->seconds();

        $response = $this->actingAs($other)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertViewIs('diagrams.show');
        $this->assertSame($other->id, $diagram->fresh()->locked_by);
    }

    public function test_heartbeat_keeps_the_lock_alive_past_the_inactivity_timeout(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $this->travel(60)->seconds();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/heartbeat')->assertOk();
        $this->travel(60)->seconds();

        $response = $this->actingAs($other)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertViewIs('diagrams.locked');
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_only_the_current_lock_holder_can_send_a_heartbeat(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $response = $this->actingAs($other)->postJson('/diagrams/'.$diagram->slug.'/heartbeat');

        $response->assertStatus(403);
    }

    public function test_forking_from_the_lock_screen_creates_an_independent_diagram_with_copied_content(): void
    {
        $holder = User::factory()->create();
        $blocked = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $blocked);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);
        Storage::disk('diagrams')->put($diagram->disk_path, '<bpmn:definitions>original content</bpmn:definitions>');

        $response = $this->actingAs($blocked)->post('/diagrams/'.$diagram->slug.'/fork');

        $response->assertRedirect();
        $forked = Diagram::where('forked_from_id', $diagram->id)->firstOrFail();
        $this->assertSame($blocked->id, $forked->author_id);
        $this->assertNotSame($diagram->slug, $forked->slug);
        $this->assertNull($forked->locked_by);
        $this->assertSame(
            Storage::disk('diagrams')->get($diagram->disk_path),
            Storage::disk('diagrams')->get($forked->disk_path)
        );
    }

    public function test_a_user_without_permission_to_view_the_diagram_cannot_fork_it(): void
    {
        $holder = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();

        $response = $this->actingAs($outsider)->post('/diagrams/'.$diagram->slug.'/fork');

        $response->assertForbidden();
        $this->assertDatabaseMissing('diagrams', ['forked_from_id' => $diagram->id]);
    }

    public function test_requesting_the_lock_when_it_is_free_acquires_it_directly(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/lock/request');

        $response->assertOk()->assertJson(['state' => 'acquired']);
        $this->assertSame($user->id, $diagram->fresh()->locked_by);
    }

    public function test_requesting_a_lock_held_by_someone_else_waits_until_the_holder_fails_to_respond(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $waiting = $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');
        $waiting->assertOk()->assertJson(['state' => 'waiting']);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);

        $this->travel(Diagram::LOCK_REQUEST_TRANSFER_SECONDS + 1)->seconds();

        $transferred = $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');
        $transferred->assertOk()->assertJson(['state' => 'acquired']);
        $this->assertSame($requester->id, $diagram->fresh()->locked_by);
    }

    public function test_a_heartbeat_from_the_holder_cancels_a_pending_lock_request(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')
            ->assertJson(['state' => 'waiting']);

        $this->travel(20)->seconds();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/heartbeat')->assertOk();
        $this->travel(20)->seconds();

        $response = $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');

        $response->assertOk()->assertJson(['state' => 'waiting']);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_an_administrator_can_force_release_a_lock_held_by_someone_else(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $response = $this->actingAs($admin)->deleteJson('/diagrams/'.$diagram->slug.'/lock');

        $response->assertOk()->assertJson(['unlocked' => true]);
        $this->assertNull($diagram->fresh()->locked_by);
    }

    public function test_a_non_admin_cannot_release_a_lock_held_by_someone_else(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $response = $this->actingAs($other)->deleteJson('/diagrams/'.$diagram->slug.'/lock');

        $response->assertStatus(403);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }
}
