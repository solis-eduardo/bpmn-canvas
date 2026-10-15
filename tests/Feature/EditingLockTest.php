<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Notifications\DiagramLockRequested;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
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
        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);
    }

    public function test_rendering_the_editor_does_not_change_the_lock_state(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertSee('data-lock-acquire-url="'.url('/diagrams/'.$diagram->slug.'/lock').'"', false);
        $this->assertNull($diagram->fresh()->locked_by);
        $this->assertNull($diagram->fresh()->locked_at);
        $this->assertNull($diagram->fresh()->last_heartbeat_at);
    }

    public function test_the_editor_acquires_a_free_lock_with_a_post_for_an_authorized_user(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/lock');

        $response->assertOk()->assertExactJson(['state' => 'acquired']);
        $this->assertSame($user->id, $diagram->fresh()->locked_by);
        $this->assertNotNull($diagram->fresh()->locked_at);
        $this->assertNotNull($diagram->fresh()->last_heartbeat_at);
    }

    public function test_the_editor_post_is_refused_when_another_user_holds_the_lock_and_requests_nothing(): void
    {
        Notification::fake();
        $holder = User::factory()->create(['name' => 'Ana Author']);
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $response = $this->actingAs($other)->postJson('/diagrams/'.$diagram->slug.'/lock');

        $response->assertStatus(423);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
        $this->assertNull($diagram->fresh()->lock_requested_by);
        $this->assertNull($diagram->fresh()->lock_requested_at);
        Notification::assertNothingSent();
    }

    /**
     * An Administrator who is neither Owner, Collaborator nor Member only sees
     * and opens the Diagram in the read-only Viewer (ADR-0006), so it can't
     * acquire the Lock.
     */
    public function test_a_viewer_cannot_acquire_the_lock_with_a_post(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $viewer = User::factory()->create(['is_admin' => true]);

        $this->actingAs($viewer)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertForbidden();
        $this->assertNull($diagram->fresh()->locked_by);
    }

    public function test_the_editor_offers_an_explicit_lock_release_action(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertSee('id="btn-release-lock"', false);
    }

    public function test_a_user_can_keep_multiple_locks_across_navigation_and_editor_closure(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $first = Diagram::factory()->for($holder, 'author')->create(['title' => 'First']);
        $second = Diagram::factory()->for($holder, 'author')->create(['title' => 'Second']);
        $this->approveCollaborator($first, $other);
        $this->approveCollaborator($second, $other);

        $this->actingAs($holder)->postJson('/diagrams/'.$first->slug.'/lock')->assertOk();
        $this->actingAs($holder)->get('/diagrams')->assertOk();
        $this->actingAs($holder)->postJson('/diagrams/'.$second->slug.'/lock')->assertOk();

        // No further Editor requests simulate closing the holder's tabs.
        $this->travel(91)->seconds();

        $firstResponse = $this->actingAs($other)->get('/diagrams/'.$first->slug);
        $secondResponse = $this->actingAs($other)->get('/diagrams/'.$second->slug);

        $firstResponse->assertViewIs('shares.show')->assertSee($holder->name);
        $secondResponse->assertViewIs('shares.show')->assertSee($holder->name);
        $this->assertSame($holder->id, $first->fresh()->locked_by);
        $this->assertSame($holder->id, $second->fresh()->locked_by);
    }

    public function test_a_second_user_is_blocked_from_opening_an_actively_locked_diagram_and_sees_who_holds_the_lock(): void
    {
        $holder = User::factory()->create(['name' => 'Ana Author']);
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);

        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

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
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $response = $this->actingAs($viewer)->get('/diagrams');

        $response->assertOk();
        $response->assertSee('Bruno Blocker');
    }

    public function test_the_listing_continues_to_indicate_a_lock_after_inactivity(): void
    {
        $holder = User::factory()->create(['name' => 'Carla Tired']);
        $viewer = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->travel(91)->seconds();

        $response = $this->actingAs($viewer)->get('/diagrams');

        $response->assertOk();
        $response->assertSee('Carla Tired');
    }

    public function test_explicitly_releasing_the_lock_allows_another_user_to_open_the_editor(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($holder)->deleteJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->assertNull($diagram->fresh()->locked_by);

        $this->actingAs($other)->get('/diagrams/'.$diagram->slug)
            ->assertOk()
            ->assertViewIs('diagrams.show');
        $this->assertNull($diagram->fresh()->locked_by);

        $this->actingAs($other)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->assertSame($other->id, $diagram->fresh()->locked_by);
    }

    public function test_an_inactive_lock_remains_held_until_released_or_expired(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->travel(91)->seconds();

        $response = $this->actingAs($other)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertViewIs('shares.show');
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_heartbeat_keeps_the_lock_alive_past_the_inactivity_timeout(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->travel(60)->seconds();
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->travel(60)->seconds();

        $response = $this->actingAs($other)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertViewIs('shares.show');
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_only_the_current_lock_holder_can_send_a_heartbeat(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $response = $this->actingAs($other)->patchJson('/diagrams/'.$diagram->slug.'/lock');

        $response->assertStatus(403);
    }

    public function test_forking_from_the_lock_screen_creates_an_independent_diagram_with_copied_content(): void
    {
        config(['app.locale' => 'pt_BR']);
        $holder = User::factory()->create();
        $blocked = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $blocked);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        Storage::disk('diagrams')->put($diagram->disk_path, '<bpmn:definitions>original content</bpmn:definitions>');

        $response = $this->actingAs($blocked)->post('/diagrams/'.$diagram->slug.'/fork');

        $response->assertRedirect();
        $forked = Diagram::where('forked_from_id', $diagram->id)->firstOrFail();
        $this->assertSame($blocked->id, $forked->author_id);
        $this->assertNotSame($diagram->slug, $forked->slug);
        $this->assertSame('Cópia de '.$diagram->title, $forked->title);
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

    public function test_requesting_a_lock_notifies_the_holder_but_never_transfers_it_automatically(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $waiting = $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');
        $waiting->assertOk()->assertJson(['state' => 'waiting']);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);

        $this->travel(31)->seconds();

        $stillWaiting = $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');
        $stillWaiting->assertOk()->assertJson(['state' => 'waiting']);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_a_heartbeat_from_the_holder_cancels_a_pending_lock_request(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')
            ->assertJson(['state' => 'waiting']);

        $this->travel(20)->seconds();
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
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
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($admin)->get('/diagrams/'.$diagram->slug)
            ->assertOk()
            ->assertSee('id="btn-release-lock"', false)
            ->assertSee(__('messages.force_release_lock'));

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
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $response = $this->actingAs($other)->deleteJson('/diagrams/'.$diagram->slug.'/lock');

        $response->assertStatus(403);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    /**
     * The blocked screen shows the holder and since when they've held the
     * Lock, and offers requesting it (issue #9).
     */
    public function test_the_blocked_screen_shows_the_holder_and_when_the_lock_was_taken(): void
    {
        $holder = User::factory()->create(['name' => 'Dora Detain']);
        $blocked = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $blocked);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $lockedAt = $diagram->fresh()->locked_at;

        $response = $this->actingAs($blocked)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertViewIs('shares.show');
        $response->assertSee('Dora Detain');
        $response->assertSee($lockedAt->toIso8601String(), false);
        $response->assertSee('id="btn-request-lock"', false);
        $response->assertSee('bg-red-100', false);
        $response->assertDontSee('id="btn-save"', false);
    }

    public function test_a_user_without_edit_permission_is_turned_away_from_the_diagram(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($stranger)->get('/diagrams/'.$diagram->slug)->assertForbidden();
        $this->actingAs($stranger)->get('/diagrams')->assertDontSee($diagram->title);
    }

    /**
     * A "waiting" response includes the server's request timestamp, but
     * deliberately exposes no transfer deadline.
     */
    public function test_requesting_a_held_lock_exposes_the_servers_request_timestamp(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->travelTo(now()->addMinute());
        $response = $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');

        $response->assertOk()->assertJson(['state' => 'waiting']);
        $requestedAt = $response->json('requested_at');
        $this->assertNotNull($requestedAt);
        $this->assertSame(
            now()->toIso8601String(),
            Carbon::parse($requestedAt)->toIso8601String()
        );
        $response->assertJsonMissingPath('expires_at');
    }

    /**
     * The Lock holder's heartbeat response carries whether someone has
     * requested the Lock, so the Editor can show a visible notice without a
     * second poll loop (issue #9).
     */
    public function test_the_holders_heartbeat_reports_a_pending_lock_request(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create(['name' => 'Rita Requester']);
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')
            ->assertJson(['state' => 'waiting']);

        $response = $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock');

        $response->assertOk()->assertJson([
            'heartbeat' => true,
            'lock_requested_by' => 'Rita Requester',
        ]);
    }

    /**
     * A heartbeat counts as the holder responding and cancels the pending
     * request (pre-existing behavior) — so once reported, a later
     * heartbeat, with nothing new pending, reports none.
     */
    public function test_the_holders_heartbeat_reports_no_pending_request_once_it_has_been_cancelled(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')
            ->assertJson(['state' => 'waiting']);

        // First heartbeat after the request: still reports it, then cancels it.
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')
            ->assertJson(['lock_requested_by' => $requester->name]);

        // Second heartbeat: nothing pending anymore.
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')
            ->assertJson(['lock_requested_by' => null]);
    }

    public function test_the_holder_can_transfer_the_lock_in_answer_to_a_request(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')
            ->assertJson(['state' => 'waiting']);

        $response = $this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/lock', ['user_id' => $requester->id]);

        $response->assertOk()->assertJsonPath('holder.id', $requester->id);
        $this->assertSame($requester->id, $diagram->fresh()->locked_by);
        $this->assertNull($diagram->fresh()->lock_requested_by);
    }

    public function test_the_holder_can_transfer_the_lock_spontaneously(): void
    {
        $holder = User::factory()->create();
        $colleague = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $colleague);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/lock', ['user_id' => $colleague->id])
            ->assertOk();

        $this->assertSame($colleague->id, $diagram->fresh()->locked_by);
    }

    public function test_an_administrator_can_force_the_transfer_of_a_lock(): void
    {
        $holder = User::factory()->create();
        $colleague = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $colleague);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($admin)->putJson('/diagrams/'.$diagram->slug.'/lock', ['user_id' => $colleague->id])
            ->assertOk();

        $this->assertSame($colleague->id, $diagram->fresh()->locked_by);
    }

    public function test_someone_who_is_neither_holder_nor_administrator_cannot_transfer_the_lock(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($other)->putJson('/diagrams/'.$diagram->slug.'/lock', ['user_id' => $other->id])
            ->assertForbidden();

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_the_lock_cannot_be_transferred_to_someone_who_cannot_edit(): void
    {
        $holder = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/lock', ['user_id' => $outsider->id])
            ->assertForbidden();

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_the_lock_state_reports_the_holder_and_the_pending_request(): void
    {
        $holder = User::factory()->create(['name' => 'Hal Holder']);
        $requester = User::factory()->create(['name' => 'Rita Request']);
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');

        $this->actingAs($requester)->getJson('/diagrams/'.$diagram->slug.'/lock')
            ->assertOk()
            ->assertJsonPath('held', true)
            ->assertJsonPath('holder.name', 'Hal Holder')
            ->assertJsonPath('requested_by', 'Rita Request');
    }

    public function test_a_refused_transfer_does_not_reveal_whether_the_user_id_exists(): void
    {
        $holder = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $known = $this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/lock', ['user_id' => $outsider->id]);
        $unknown = $this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/lock', ['user_id' => 999999]);

        $known->assertForbidden();
        $unknown->assertForbidden();
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    /**
     * A stale Editor tab whose Lock expired must not overwrite whoever took
     * the Lock over in the meantime (ADR-0003).
     */
    public function test_a_former_holder_cannot_save_once_their_expired_lock_was_taken_by_someone_else(): void
    {
        $former = User::factory()->create();
        $newHolder = User::factory()->create();
        $diagram = Diagram::factory()->for($former, 'author')->create();
        $this->approveCollaborator($diagram, $newHolder);

        $this->actingAs($former)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        Carbon::setTestNow(now()->addMinutes(config('bpmn.lock_ttl_minutes') + 1));
        $this->artisan('locks:expire');
        $this->actingAs($newHolder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $response = $this->actingAs($former)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">stale</bpmn:definitions>',
        ]);

        $response->assertStatus(423);
        $this->assertSame($newHolder->id, (int) $diagram->fresh()->locked_by);
    }

    /**
     * Saving into a free Lock claims it, so content is never written outside
     * a Lock the saver holds.
     */
    public function test_saving_while_the_lock_is_free_acquires_it_for_the_saver(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->assertNull($diagram->fresh()->locked_by);

        $this->actingAs($owner)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">saved</bpmn:definitions>',
        ])->assertOk();

        $this->assertSame($owner->id, (int) $diagram->fresh()->locked_by);
    }

    /** Invalid content is refused without claiming the Lock as a side effect. */
    public function test_a_rejected_save_does_not_acquire_the_lock(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($owner)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => 'not xml',
        ])->assertStatus(422);

        $this->assertNull($diagram->fresh()->locked_by);
    }

    /**
     * The banner only reaches a holder whose Editor is still open, so the
     * request also notifies them (CONTEXT.md, "Lock").
     */
    public function test_requesting_the_lock_notifies_the_holder(): void
    {
        Notification::fake();
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')->assertOk();

        Notification::assertSentTo($holder, DiagramLockRequested::class);
    }

    /** Polling the blocked screen must not notify the holder over and over. */
    public function test_repeating_a_pending_lock_request_does_not_notify_the_holder_again(): void
    {
        Notification::fake();
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');
        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');
        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');

        Notification::assertSentToTimes($holder, DiagramLockRequested::class, 1);
    }

    /**
     * The holder's Heartbeat clears the pending request, so the requester's
     * next poll registers it again; that must not notify a second time.
     */
    public function test_a_request_renewed_after_a_heartbeat_does_not_notify_the_holder_again(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')->assertOk();
            $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        }

        $this->assertSame(1, $holder->notifications()->where('type', DiagramLockRequested::class)->count());
    }

    /** A request is new again once the holder has read the earlier one. */
    public function test_a_new_request_after_the_holder_read_the_notification_notifies_again(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');
        $holder->notifications()->first()->markAsRead();
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock');
        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request');

        $this->assertSame(2, $holder->notifications()->where('type', DiagramLockRequested::class)->count());
    }

    /** The way out of someone else's Lock is offered on the blocked screen. */
    public function test_the_blocked_screen_offers_a_fork(): void
    {
        $holder = User::factory()->create();
        $blocked = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $blocked);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $response = $this->actingAs($blocked)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertSee(route('diagrams.fork', $diagram->slug), false);
        $response->assertSee(__('messages.fork_diagram'), false);
    }

    /** A Fork is available at any time, so the read-only Viewer offers it too. */
    public function test_the_read_only_viewer_offers_a_fork(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $response = $this->actingAs($admin)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertSee(route('diagrams.fork', $diagram->slug), false);
    }

    /** Acquiring a free Lock notifies nobody — there is no holder to tell. */
    public function test_acquiring_a_free_lock_notifies_nobody(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/lock/request')->assertOk();

        Notification::assertNothingSent();
    }

    /**
     * Claiming a free Lock is one conditional write, so of two concurrent
     * savers only one can win it.
     */
    public function test_only_one_of_two_concurrent_claims_on_a_free_lock_wins(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $diagram = Diagram::factory()->for($first, 'author')->create();
        $this->approveCollaborator($diagram, $second);

        // Both read the Diagram while the Lock is free, as two requests
        // racing between the check and the claim would.
        $firstView = Diagram::findOrFail($diagram->id);
        $secondView = Diagram::findOrFail($diagram->id);

        $this->assertTrue($firstView->claimFreeLock($first));
        $this->assertFalse($secondView->claimFreeLock($second));
        $this->assertSame($first->id, (int) $diagram->fresh()->locked_by);
    }

    /** The loser of that race is told the Lock is gone, not silently merged. */
    public function test_saving_against_a_lock_taken_between_check_and_write_is_refused(): void
    {
        $holder = User::factory()->create();
        $saver = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $saver);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $response = $this->actingAs($saver)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">racing</bpmn:definitions>',
        ]);

        $response->assertStatus(423);
    }

    /**
     * Pins the Diagram's updated_at ("Modified" in the Diagram list) to a
     * known past instant, so any later bump is visible.
     */
    private function rewindModifiedAt(Diagram $diagram): Carbon
    {
        $past = now()->subDay()->startOfSecond();

        $diagram->forceFill(['updated_at' => $past])->saveQuietly();

        return $past;
    }

    public function test_opening_the_editor_does_not_change_the_diagrams_modified_time(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $past = $this->rewindModifiedAt($diagram);

        $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->assertSame($user->id, $diagram->fresh()->locked_by);
        $this->assertTrue($diagram->fresh()->updated_at->equalTo($past));
    }

    public function test_heartbeats_do_not_change_the_diagrams_modified_time(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $past = $this->rewindModifiedAt($diagram);

        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $draft = Diagram::emptyBpmn();

        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', [
            'draft' => $draft,
        ])->assertOk();

        $this->assertSame($draft, $diagram->fresh()->lock_draft);
        $this->assertTrue($diagram->fresh()->updated_at->equalTo($past));
    }

    public function test_a_blocked_lock_request_does_not_change_the_diagrams_modified_time(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $requester);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $past = $this->rewindModifiedAt($diagram);

        $this->actingAs($requester)->postJson('/diagrams/'.$diagram->slug.'/lock/request')->assertOk();

        $this->assertSame($requester->id, $diagram->fresh()->lock_requested_by);
        $this->assertTrue($diagram->fresh()->updated_at->equalTo($past));
    }

    public function test_releasing_the_lock_does_not_change_the_diagrams_modified_time(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $past = $this->rewindModifiedAt($diagram);

        $this->actingAs($holder)->deleteJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->assertNull($diagram->fresh()->locked_by);
        $this->assertTrue($diagram->fresh()->updated_at->equalTo($past));
    }

    public function test_transferring_the_lock_does_not_change_the_diagrams_modified_time(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $past = $this->rewindModifiedAt($diagram);

        $this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/lock', [
            'user_id' => $other->id,
        ])->assertOk();

        $this->assertSame($other->id, $diagram->fresh()->locked_by);
        $this->assertTrue($diagram->fresh()->updated_at->equalTo($past));
    }

    public function test_saving_a_new_version_changes_the_diagrams_modified_time(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());
        $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $past = $this->rewindModifiedAt($diagram);

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => str_replace('name="', 'name="Changed ', Diagram::emptyBpmn()),
        ])->assertOk();

        $this->assertTrue($diagram->fresh()->updated_at->greaterThan($past));
    }

    public function test_saving_unchanged_content_does_not_change_the_diagrams_modified_time(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());
        $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $past = $this->rewindModifiedAt($diagram);

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => Diagram::emptyBpmn(),
        ])->assertOk();

        $this->assertTrue($diagram->fresh()->updated_at->equalTo($past));
    }

    /**
     * Restoring rewrites the content, so it is refused like a save while
     * someone else holds the Lock, and the holder's draft survives it.
     */
    public function test_restoring_a_version_is_refused_while_another_user_holds_the_lock(): void
    {
        $holder = User::factory()->create();
        $restorer = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $restorer);
        app(VersionRepository::class)->save($diagram, $holder, '<bpmn:definitions>later</bpmn:definitions>');
        $oldestVersionId = app(VersionRepository::class)->list($diagram)[1]['id'];
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $diagram->fresh()->heartbeat($holder, '<bpmn:definitions>unsaved draft</bpmn:definitions>');
        $contentBefore = Storage::disk('diagrams')->get($diagram->disk_path);

        $response = $this->actingAs($restorer)->postJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$oldestVersionId.'/restore',
        );

        $response->assertStatus(423);
        $this->assertSame($contentBefore, Storage::disk('diagrams')->get($diagram->fresh()->disk_path));
        $this->assertSame($holder->id, (int) $diagram->fresh()->locked_by);
        $this->assertSame('<bpmn:definitions>unsaved draft</bpmn:definitions>', $diagram->fresh()->lock_draft);
    }

    /**
     * The Lock holder can restore, and the restore keeps the Lock with them.
     */
    public function test_the_lock_holder_can_restore_a_version(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        app(VersionRepository::class)->save($diagram, $holder, '<bpmn:definitions>later</bpmn:definitions>');
        $oldestVersionId = app(VersionRepository::class)->list($diagram)[1]['id'];
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->actingAs($holder)->postJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$oldestVersionId.'/restore',
        )->assertOk()->assertJson(['restored' => true]);

        $this->assertSame($holder->id, (int) $diagram->fresh()->locked_by);
    }

    /**
     * Restoring into a free Lock takes it, like saving content does.
     */
    public function test_restoring_into_a_free_lock_claims_it(): void
    {
        $editor = User::factory()->create();
        $diagram = Diagram::factory()->for($editor, 'author')->create();
        app(VersionRepository::class)->save($diagram, $editor, '<bpmn:definitions>later</bpmn:definitions>');
        $oldestVersionId = app(VersionRepository::class)->list($diagram)[1]['id'];
        $this->assertNull($diagram->fresh()->locked_by);

        $this->actingAs($editor)->postJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$oldestVersionId.'/restore',
        )->assertOk();

        $this->assertSame($editor->id, (int) $diagram->fresh()->locked_by);
    }

    /**
     * An unknown Version is a 404 that leaves the free Lock alone.
     */
    public function test_restoring_an_unknown_version_does_not_take_a_free_lock(): void
    {
        $editor = User::factory()->create();
        $diagram = Diagram::factory()->for($editor, 'author')->create();

        $this->actingAs($editor)->postJson(
            '/diagrams/'.$diagram->slug.'/versions/'.str_repeat('a', 40).'/restore',
        )->assertNotFound();

        $this->assertNull($diagram->fresh()->locked_by);
    }

    /**
     * Nobody is keeping an expired Lock alive, so entering the Diagram takes
     * it over at once instead of waiting for `locks:expire` (ADR-0003). The
     * former holder's draft is kept as a provisional Version, as expiry does.
     */
    public function test_entering_a_diagram_whose_lock_expired_takes_it_over_and_keeps_the_former_drafts(): void
    {
        $former = User::factory()->create();
        $taker = User::factory()->create();
        $diagram = Diagram::factory()->for($former, 'author')->create();
        $this->approveCollaborator($diagram, $taker);
        $versions = app(VersionRepository::class);
        $before = count($versions->list($diagram));
        $this->actingAs($former)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->actingAs($former)->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => preg_replace('/ name="[^"]*"/', ' name="Unsaved"', Diagram::emptyBpmn(), 1)])->assertOk();

        $this->travel(config('bpmn.lock_ttl_minutes'))->minutes();

        $this->actingAs($taker)->get('/diagrams/'.$diagram->slug)->assertOk()->assertViewIs('diagrams.show');
        $this->actingAs($taker)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk()->assertJson(['state' => 'acquired']);

        $this->assertSame($taker->id, $diagram->fresh()->locked_by);
        $list = $versions->list($diagram->fresh());
        $this->assertCount($before + 1, $list);
        $this->assertTrue($list[0]['provisional']);
        $this->assertSame($former->name, $list[0]['author']);

        $this->actingAs($former)->patchJson('/diagrams/'.$diagram->slug.'/lock')->assertForbidden();
        $this->assertSame($taker->id, $diagram->fresh()->locked_by);
    }

    public function test_a_lock_within_the_ttl_still_refuses_the_acquisition(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagram, $other);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $this->travel(config('bpmn.lock_ttl_minutes') - 1)->minutes();

        $this->actingAs($other)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertStatus(423);
        $this->actingAs($other)->get('/diagrams/'.$diagram->slug)->assertViewIs('shares.show');
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }
}
