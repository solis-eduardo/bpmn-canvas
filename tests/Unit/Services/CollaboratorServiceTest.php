<?php

namespace Tests\Unit\Services;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Notifications\DiagramAccessRequested;
use App\Notifications\DiagramAccessRequestResolved;
use App\Notifications\DiagramCollaboratorInvited;
use App\Notifications\DiagramCollaboratorRemoved;
use App\Services\CollaboratorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CollaboratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private CollaboratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->service = app(CollaboratorService::class);
    }

    public function test_grant_approves_and_invites(): void
    {
        $diagram = Diagram::factory()->create();
        $target = User::factory()->create();

        $collaborator = $this->service->grant($diagram, $target);

        $this->assertSame(Collaborator::STATUS_APPROVED, $collaborator->status);
        Notification::assertSentTo($target, DiagramCollaboratorInvited::class);
    }

    public function test_grant_approves_a_pending_request_and_invites(): void
    {
        $diagram = Diagram::factory()->create();
        $target = User::factory()->create();
        $this->service->requestAccess($diagram, $target);

        $collaborator = $this->service->grant($diagram, $target);

        $this->assertSame(Collaborator::STATUS_APPROVED, $collaborator->status);
        Notification::assertSentTo($target, DiagramCollaboratorInvited::class);
    }

    public function test_granting_again_notifies_no_one(): void
    {
        $diagram = Diagram::factory()->create();
        $target = User::factory()->create();
        $this->service->grant($diagram, $target);

        $this->service->grant($diagram, $target);

        Notification::assertSentToTimes($target, DiagramCollaboratorInvited::class, 1);
    }

    public function test_request_access_stays_pending_and_tells_the_diagram_owner(): void
    {
        $diagram = Diagram::factory()->create();
        $requester = User::factory()->create();

        $collaborator = $this->service->requestAccess($diagram, $requester);

        $this->assertSame(Collaborator::STATUS_REQUESTED, $collaborator->status);
        Notification::assertSentTo($diagram->owner, DiagramAccessRequested::class);
        Notification::assertNotSentTo($requester, DiagramCollaboratorInvited::class);
    }

    public function test_repeating_a_request_does_not_notify_again(): void
    {
        $diagram = Diagram::factory()->create();
        $requester = User::factory()->create();

        $this->service->requestAccess($diagram, $requester);
        $this->service->requestAccess($diagram, $requester);

        Notification::assertSentToTimes($diagram->owner, DiagramAccessRequested::class, 1);
        $this->assertSame(1, Collaborator::count());
    }

    public function test_approve_resolves_a_pending_request(): void
    {
        $diagram = Diagram::factory()->create();
        $requester = User::factory()->create();
        $this->service->requestAccess($diagram, $requester);

        $collaborator = $this->service->approve($diagram, $requester);

        $this->assertSame(Collaborator::STATUS_APPROVED, $collaborator->status);
        Notification::assertSentTo($requester, DiagramAccessRequestResolved::class, fn ($n) => $n->toArray($requester)['approved'] === true);
    }

    public function test_approving_an_approved_collaborator_notifies_no_one(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        $this->service->grant($diagram, $user);

        $this->service->approve($diagram, $user);

        Notification::assertNotSentTo($user, DiagramAccessRequestResolved::class);
    }

    public function test_approving_someone_who_never_asked_fails(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->approve(Diagram::factory()->create(), User::factory()->create());
    }

    public function test_revoking_a_pending_request_declines_it(): void
    {
        $diagram = Diagram::factory()->create();
        $requester = User::factory()->create();
        $this->service->requestAccess($diagram, $requester);

        $this->service->revoke($diagram, $requester);

        $this->assertSame(0, Collaborator::count());
        Notification::assertSentTo($requester, DiagramAccessRequestResolved::class, fn ($n) => $n->toArray($requester)['approved'] === false);
    }

    public function test_revoking_an_approved_collaborator_removes_and_tells_them(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        $this->service->grant($diagram, $user);

        $this->service->revoke($diagram, $user);

        $this->assertSame(0, Collaborator::count());
        Notification::assertSentTo($user, DiagramCollaboratorRemoved::class);
    }

    public function test_revoking_someone_with_no_row_notifies_no_one(): void
    {
        $user = User::factory()->create();

        $this->service->revoke(Diagram::factory()->create(), $user);

        Notification::assertNothingSent();
    }
}
