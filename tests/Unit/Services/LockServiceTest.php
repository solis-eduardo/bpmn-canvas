<?php

namespace Tests\Unit\Services;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use App\Services\LockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LockServiceTest extends TestCase
{
    use RefreshDatabase;

    private LockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LockService::class);
    }

    public function test_a_free_diagram_reports_no_holder(): void
    {
        $state = $this->service->state(Diagram::factory()->create());

        $this->assertFalse($state->isHeld());
        $this->assertNull($state->requester);
    }

    public function test_state_reports_the_holder_and_the_pending_request(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);
        $this->service->openOrRequest($diagram, $requester);

        $state = $this->service->state($diagram->fresh());

        $this->assertTrue($state->isHeldBy($holder));
        $this->assertFalse($state->isHeldBy($requester));
        $this->assertTrue($state->requester->is($requester));
        $this->assertNotNull($state->requestedAt);
    }

    public function test_opening_a_free_diagram_acquires_the_lock(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->create();

        $outcome = $this->service->openOrRequest($diagram, $user);

        $this->assertTrue($outcome->acquired);
        $this->assertSame(['state' => 'acquired'], $outcome->toArray());
        $this->assertSame($user->id, $diagram->fresh()->locked_by);
    }

    public function test_the_holder_opening_again_keeps_the_lock(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $user);

        $this->assertTrue($this->service->openOrRequest($diagram, $user)->acquired);
        $this->assertNull($diagram->fresh()->lock_requested_by);
    }

    public function test_opening_a_locked_diagram_registers_a_request_without_moving_the_lock(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);

        $outcome = $this->service->openOrRequest($diagram, $other);

        $this->assertFalse($outcome->acquired);
        $this->assertSame('waiting', $outcome->toArray()['state']);
        $this->assertNotEmpty($outcome->toArray()['requested_at']);
        $fresh = $diagram->fresh();
        $this->assertSame($holder->id, $fresh->locked_by);
        $this->assertSame($other->id, $fresh->lock_requested_by);
    }

    public function test_a_repeated_request_keeps_its_original_timestamp(): void
    {
        $other = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, User::factory()->create());

        $first = $this->service->openOrRequest($diagram, $other);
        $this->travel(5)->minutes();
        $second = $this->service->openOrRequest($diagram->fresh(), $other);

        $this->assertSame($first->requestedAt->toIso8601String(), $second->requestedAt->toIso8601String());
    }

    public function test_renew_only_works_for_the_holder(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);

        $this->assertTrue($this->service->renew($diagram, $holder));
        $this->assertFalse($this->service->renew($diagram, User::factory()->create()));
    }

    public function test_renew_clears_a_pending_request(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);
        $this->service->openOrRequest($diagram, User::factory()->create());

        $this->service->renew($diagram->fresh(), $holder);

        $this->assertNull($diagram->fresh()->lock_requested_by);
    }

    public function test_transfer_answers_an_existing_request(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);
        $this->service->openOrRequest($diagram, $requester);

        $this->service->transfer($diagram->fresh(), $requester);

        $fresh = $diagram->fresh();
        $this->assertSame($requester->id, $fresh->locked_by);
        $this->assertNull($fresh->lock_requested_by);
        $this->assertNull($fresh->lock_requested_at);
    }

    public function test_transfer_works_without_a_prior_request(): void
    {
        $holder = User::factory()->create();
        $recipient = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);

        $this->service->transfer($diagram, $recipient);

        $this->assertSame($recipient->id, $diagram->fresh()->locked_by);
        $this->assertNotNull($diagram->fresh()->last_heartbeat_at);
    }

    public function test_transfer_does_not_leave_the_lock_free_in_between_hands(): void
    {
        $holder = User::factory()->create();
        $recipient = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);
        $queries = [];
        \DB::listen(function ($query) use (&$queries) {
            if (str_starts_with($query->sql, 'update')) {
                $queries[] = $query->sql;
            }
        });

        $this->service->transfer($diagram, $recipient);

        $this->assertCount(1, $queries);
    }

    public function test_release_frees_the_lock_and_drops_the_request(): void
    {
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, User::factory()->create());
        $this->service->openOrRequest($diagram, User::factory()->create());

        $this->service->release($diagram->fresh());

        $fresh = $diagram->fresh();
        $this->assertNull($fresh->locked_by);
        $this->assertNull($fresh->lock_requested_by);
    }

    public function test_heartbeat_reports_the_pending_requester_and_clears_the_request(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->openOrRequest($diagram, $holder);
        $this->service->openOrRequest($diagram, $requester);

        $reported = $this->service->heartbeat($diagram->fresh(), $holder);

        $this->assertTrue($reported->is($requester));
        $this->assertNull($diagram->fresh()->lock_requested_by);
    }

    public function test_acquire_keeps_the_original_lock_time_for_the_current_holder(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->acquire($diagram, $holder);
        $lockedAt = $diagram->fresh()->locked_at;

        $this->travel(5)->minutes();
        $this->service->acquire($diagram->fresh(), $holder);

        $this->assertEquals($lockedAt, $diagram->fresh()->locked_at);
    }

    public function test_acquire_is_refused_while_someone_else_holds_the_lock(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $this->service->acquire($diagram, $holder);

        $this->assertFalse($this->service->acquire($diagram->fresh(), $other));
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
        $this->assertNull($diagram->fresh()->lock_requested_by);
    }

    public function test_reconcile_releases_the_lock_of_a_holder_who_lost_edit_rights(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $diagram->collaborators()->create([
            'user_id' => $holder->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);
        $this->service->openOrRequest($diagram, $holder);
        $this->service->renew($diagram, $holder, '<definitions/>');
        $this->service->openOrRequest($diagram->fresh(), User::factory()->create());
        $diagram->collaborators()->where('user_id', $holder->id)->delete();

        $this->service->reconcile($diagram);

        $fresh = $diagram->fresh();
        $this->assertNull($fresh->locked_by);
        $this->assertNull($fresh->lock_draft);
        $this->assertNull($fresh->lock_requested_by);
    }

    public function test_reconcile_keeps_the_lock_while_the_holder_can_still_edit(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'owner')->create();
        $this->service->openOrRequest($diagram, $holder);

        $this->service->reconcile($diagram);

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    /**
     * Both Users loaded the Diagram while the Lock was free; the first to
     * write takes it, and the second must not overwrite that holder.
     */
    public function test_opening_a_diagram_another_user_just_locked_waits_instead_of_overwriting(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $stale = Diagram::findOrFail($diagram->id);

        $this->service->openOrRequest($diagram, $first);
        $outcome = $this->service->openOrRequest($stale, $second);

        $this->assertFalse($outcome->acquired);
        $this->assertSame($first->id, $diagram->fresh()->locked_by);
        $this->assertSame($second->id, $diagram->fresh()->lock_requested_by);
    }

    /** Acquiring one Diagram's Lock must not rewrite the User's other Locks. */
    public function test_acquiring_a_lock_leaves_the_users_other_locks_untouched(): void
    {
        $user = User::factory()->create();
        $held = Diagram::factory()->create();
        $free = Diagram::factory()->create();
        $this->service->openOrRequest($held, $user);
        $lockedAt = $held->fresh()->locked_at;

        $this->travel(5)->minutes();
        $this->assertTrue($this->service->openOrRequest($free, $user)->acquired);

        $this->assertEquals($lockedAt, $held->fresh()->locked_at);
        $this->assertSame($user->id, $free->fresh()->locked_by);
    }

    public function test_entering_a_diagram_another_user_just_locked_is_refused(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $stale = Diagram::findOrFail($diagram->id);

        $this->service->acquire($diagram, $first);

        $this->assertFalse($this->service->acquire($stale, $second));
        $this->assertSame($first->id, $diagram->fresh()->locked_by);
    }

    /** A Diagram whose holder's Lock went $minutes without a Heartbeat, with an unsaved draft. */
    private function diagramHeldFor(User $holder, int $minutes): Diagram
    {
        Storage::fake('diagrams');

        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->service->acquire($diagram, $holder);
        $this->service->renew($diagram, $holder, preg_replace('/ name="[^"]*"/', ' name="Unsaved"', Diagram::emptyBpmn(), 1));
        $this->travel($minutes)->minutes();

        return $diagram->fresh();
    }

    private function approved(Diagram $diagram, User $user): User
    {
        $diagram->collaborators()->create(['user_id' => $user->id, 'status' => Collaborator::STATUS_APPROVED]);

        return $user;
    }

    public function test_acquire_takes_over_an_expired_lock_held_by_someone_else(): void
    {
        $former = User::factory()->create();
        $taker = User::factory()->create();
        $diagram = $this->diagramHeldFor($former, config('bpmn.lock_ttl_minutes'));
        $this->approved($diagram, $taker);

        $this->assertTrue($this->service->acquire($diagram, $taker));

        $fresh = $diagram->fresh();
        $this->assertSame($taker->id, $fresh->locked_by);
        $this->assertNull($fresh->lock_draft);
        $this->assertNull($fresh->lock_requested_by);
        $this->assertSame($taker->id, $diagram->locked_by);
    }

    public function test_the_takeover_keeps_the_former_holders_draft_as_a_provisional_version(): void
    {
        $former = User::factory()->create();
        $taker = User::factory()->create();
        $diagram = $this->diagramHeldFor($former, config('bpmn.lock_ttl_minutes'));
        $this->approved($diagram, $taker);
        $versions = app(VersionRepository::class);
        $before = count($versions->list($diagram));

        $this->service->acquire($diagram, $taker);

        $list = $versions->list($diagram->fresh());
        $this->assertCount($before + 1, $list);
        $this->assertTrue($list[0]['provisional']);
        $this->assertSame($former->name, $list[0]['author']);
    }

    public function test_acquire_does_not_take_over_a_lock_still_within_the_ttl(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = $this->diagramHeldFor($holder, config('bpmn.lock_ttl_minutes') - 1);
        $this->approved($diagram, $other);

        $this->assertFalse($this->service->acquire($diagram, $other));

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
        $this->assertSame($holder->id, $diagram->locked_by);
        $this->assertNotNull($diagram->fresh()->lock_draft);
    }

    public function test_only_one_of_two_concurrent_takeovers_wins(): void
    {
        $former = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $diagram = $this->diagramHeldFor($former, config('bpmn.lock_ttl_minutes'));
        $this->approved($diagram, $first);
        $this->approved($diagram, $second);
        $staleForSecond = Diagram::findOrFail($diagram->id);
        $versions = app(VersionRepository::class);
        $before = count($versions->list($diagram));

        $this->assertTrue($this->service->acquire($diagram, $first));
        $this->assertFalse($this->service->acquire($staleForSecond, $second));

        $this->assertSame($first->id, $diagram->fresh()->locked_by);
        $this->assertSame($first->id, $staleForSecond->locked_by);
        $this->assertCount($before + 1, $versions->list($diagram->fresh()));
    }

    public function test_a_takeover_loses_to_the_former_holders_late_heartbeat(): void
    {
        $former = User::factory()->create();
        $taker = User::factory()->create();
        $diagram = $this->diagramHeldFor($former, config('bpmn.lock_ttl_minutes'));
        $this->approved($diagram, $taker);
        $stale = Diagram::findOrFail($diagram->id);
        $versions = app(VersionRepository::class);
        $before = count($versions->list($diagram));

        $this->assertTrue($this->service->renew(Diagram::findOrFail($diagram->id), $former));

        $this->assertFalse($this->service->acquire($stale, $taker));
        $this->assertSame($former->id, $diagram->fresh()->locked_by);
        $this->assertSame($former->id, $stale->locked_by);
        $this->assertCount($before, $versions->list($diagram->fresh()));
    }

    public function test_a_late_heartbeat_does_not_revive_a_lock_taken_over_meanwhile(): void
    {
        $former = User::factory()->create();
        $taker = User::factory()->create();
        $diagram = $this->diagramHeldFor($former, config('bpmn.lock_ttl_minutes'));
        $this->approved($diagram, $taker);
        $staleHolderView = Diagram::findOrFail($diagram->id);

        $this->service->acquire($diagram, $taker);

        $this->assertFalse($this->service->renew($staleHolderView, $former, Diagram::emptyBpmn()));
        $this->assertSame($taker->id, $diagram->fresh()->locked_by);
        $this->assertNull($diagram->fresh()->lock_draft);
    }
}
