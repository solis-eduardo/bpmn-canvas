<?php

namespace Tests\Unit\Policies;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Policies\LockPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private LockPolicy $policy;

    private Diagram $diagram;

    private User $holder;

    private User $collaborator;

    private User $admin;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new LockPolicy;
        $this->diagram = Diagram::factory()->create();
        $this->holder = $this->diagram->owner;
        $this->collaborator = User::factory()->create();
        Collaborator::create(['diagram_id' => $this->diagram->id, 'user_id' => $this->collaborator->id, 'status' => Collaborator::STATUS_APPROVED]);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->stranger = User::factory()->create();
    }

    private function lockedByOwner(): Diagram
    {
        $this->diagram->acquireLock($this->holder);

        return $this->diagram->fresh();
    }

    public function test_write_is_for_the_holder_or_an_editor_while_the_lock_is_free(): void
    {
        $this->assertTrue($this->policy->write($this->holder, $this->diagram));
        $this->assertTrue($this->policy->write($this->collaborator, $this->diagram));
        $this->assertFalse($this->policy->write($this->admin, $this->diagram));
        $this->assertFalse($this->policy->write($this->stranger, $this->diagram));

        $held = $this->lockedByOwner();

        $this->assertTrue($this->policy->write($this->holder, $held));
        $this->assertFalse($this->policy->write($this->collaborator, $held));
        $this->assertFalse($this->policy->write($this->admin, $held));
    }

    public function test_view_is_for_editors_and_administrators(): void
    {
        $this->assertTrue($this->policy->view($this->holder, $this->diagram));
        $this->assertTrue($this->policy->view($this->collaborator, $this->diagram));
        $this->assertTrue($this->policy->view($this->admin, $this->diagram));
        $this->assertFalse($this->policy->view($this->stranger, $this->diagram));
    }

    public function test_acquire_is_for_editors_only_not_administrators(): void
    {
        $this->assertTrue($this->policy->acquire($this->holder, $this->diagram));
        $this->assertTrue($this->policy->acquire($this->collaborator, $this->diagram));
        $this->assertFalse($this->policy->acquire($this->admin, $this->diagram));
        $this->assertFalse($this->policy->acquire($this->stranger, $this->diagram));
    }

    public function test_renew_is_for_the_holder_alone(): void
    {
        $this->assertFalse($this->policy->renew($this->holder, $this->diagram), 'nothing to renew when free');

        $locked = $this->lockedByOwner();

        $this->assertTrue($this->policy->renew($this->holder, $locked));
        $this->assertFalse($this->policy->renew($this->collaborator, $locked));
        $this->assertFalse($this->policy->renew($this->admin, $locked));
    }

    public function test_the_holder_can_transfer_to_an_eligible_user(): void
    {
        $locked = $this->lockedByOwner();

        $this->assertTrue($this->policy->transfer($this->holder, $locked, $this->collaborator));
    }

    public function test_an_administrator_can_transfer_without_holding_the_lock(): void
    {
        $locked = $this->lockedByOwner();

        $this->assertTrue($this->policy->transfer($this->admin, $locked, $this->collaborator));
    }

    public function test_other_editors_cannot_transfer(): void
    {
        $locked = $this->lockedByOwner();

        $this->assertFalse($this->policy->transfer($this->collaborator, $locked, $this->collaborator));
        $this->assertFalse($this->policy->transfer($this->stranger, $locked, $this->collaborator));
    }

    public function test_the_recipient_must_be_eligible_to_edit(): void
    {
        $locked = $this->lockedByOwner();

        $this->assertFalse($this->policy->transfer($this->holder, $locked, $this->stranger));
        $this->assertFalse($this->policy->transfer($this->holder, $locked, $this->admin));
    }

    public function test_a_free_lock_cannot_be_transferred(): void
    {
        $this->assertFalse($this->policy->transfer($this->holder, $this->diagram, $this->collaborator));
    }

    public function test_release_is_for_the_holder_or_an_administrator(): void
    {
        $locked = $this->lockedByOwner();

        $this->assertTrue($this->policy->release($this->holder, $locked));
        $this->assertTrue($this->policy->release($this->admin, $locked));
        $this->assertFalse($this->policy->release($this->collaborator, $locked));
        $this->assertFalse($this->policy->release($this->stranger, $locked));
    }

    public function test_releasing_a_free_lock_is_a_harmless_no_op_for_anyone(): void
    {
        $this->assertTrue($this->policy->release($this->stranger, $this->diagram));
    }

    public function test_the_abilities_are_registered_with_the_gate(): void
    {
        $locked = $this->lockedByOwner();

        $this->assertTrue($this->holder->can('lock.transfer', [$locked, $this->collaborator]));
        $this->assertFalse($this->stranger->can('lock.release', $locked));
    }

    public function test_a_transfer_to_nobody_is_refused(): void
    {
        $locked = $this->lockedByOwner();

        $this->assertFalse($this->policy->transfer($this->holder, $locked, null));
    }
}
