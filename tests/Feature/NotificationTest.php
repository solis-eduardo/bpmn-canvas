<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DiagramAccessRequested;
use App\Notifications\DiagramAccessRequestResolved;
use App\Notifications\DiagramCollaboratorInvited;
use App\Notifications\DiagramCollaboratorRemoved;
use App\Notifications\DiagramLockRequested;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use App\Notifications\WorkspaceOwnershipTransferred;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The bell end to end (issue #15): a Collaborator access request notifies
 * the Diagram Owner, the unread-count endpoint and both mark-as-read
 * actions work, and nobody can read or dismiss another User's
 * notifications.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_requesting_access_notifies_the_diagram_owner(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ])->assertCreated();

        $this->assertCount(1, $owner->fresh()->notifications);

        $notification = $owner->fresh()->notifications->first();
        $this->assertSame(DiagramAccessRequested::class, $notification->type);
        $this->assertSame($diagram->id, $notification->data['diagram_id']);
        $this->assertSame($requester->id, $notification->data['requester_id']);
        $this->assertNull($notification->read_at);
    }

    public function test_discovery_access_requests_are_pending_private_and_notify_once(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create([
            'title' => 'Requestable Diagram',
        ]);

        $this->actingAs($requester)->get('/diagrams?discover=Requestable')
            ->assertRedirect(route('diagrams.index', ['q' => 'Requestable']));

        $this->actingAs($requester)->get('/diagrams?q=Requestable')
            ->assertOk()
            ->assertSee($diagram->title);

        $this->post("/diagrams/{$diagram->slug}/collaborators?q=Requestable", ['user_id' => $requester->id])
            ->assertRedirect(route('diagrams.index', ['q' => 'Requestable']));
        $this->post("/diagrams/{$diagram->slug}/collaborators?q=Requestable", ['user_id' => $requester->id])
            ->assertRedirect(route('diagrams.index', ['q' => 'Requestable']));
        $this->get('/diagrams?q=Requestable')
            ->assertOk()
            ->assertSee(__('messages.access_request_pending'))
            ->assertDontSee('name="user_id" value="'.$requester->id.'"', false);

        $this->assertDatabaseCount('collaborators', 1);
        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => Collaborator::STATUS_REQUESTED,
        ]);
        $this->assertCount(1, $owner->fresh()->notifications);

        $this->get("/diagrams/{$diagram->slug}")->assertForbidden();
        $this->getJson("/diagrams/{$diagram->slug}/content")->assertForbidden();
    }

    public function test_a_direct_invite_notifies_the_invited_user(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $target->id,
        ])->assertCreated();

        $this->assertCount(0, $owner->fresh()->notifications);
        $this->assertCount(1, $target->fresh()->notifications);

        $notification = $target->fresh()->notifications->first();
        $this->assertSame(DiagramCollaboratorInvited::class, $notification->type);
        $this->assertSame($diagram->id, $notification->data['diagram_id']);
        $this->assertSame($diagram->slug, $notification->data['diagram_slug']);
        $this->assertSame($diagram->title, $notification->data['diagram_title']);
        $this->assertNull($notification->read_at);

        $this->actingAs($target)->getJson('/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'message' => __('messages.notification_collaborator_invited', [
                    'diagram' => $diagram->title,
                ]),
            ]);
    }

    public function test_an_administrator_direct_invite_notifies_the_invited_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($admin)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $target->id,
        ])->assertCreated();

        $this->assertSame(
            DiagramCollaboratorInvited::class,
            $target->fresh()->notifications->first()->type
        );
    }

    public function test_an_email_invite_notifies_the_existing_user_without_repeating_on_an_unchanged_grant(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'email' => $target->email,
        ])->assertCreated()->assertJson(['success' => true]);

        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'email' => $target->email,
        ])->assertCreated()->assertJson(['success' => true]);

        $this->assertCount(1, $target->fresh()->notifications);
        $this->assertSame(
            DiagramCollaboratorInvited::class,
            $target->fresh()->notifications->first()->type
        );
    }

    public function test_removing_a_collaborator_notifies_the_removed_user(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $this->actingAs($owner)->deleteJson(
            "/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}"
        )->assertOk();

        $this->assertCount(1, $collaborator->fresh()->notifications);

        $notification = $collaborator->fresh()->notifications->first();
        $this->assertSame(DiagramCollaboratorRemoved::class, $notification->type);
        $this->assertSame($diagram->id, $notification->data['diagram_id']);
        $this->assertSame($diagram->slug, $notification->data['diagram_slug']);
        $this->assertSame($diagram->title, $notification->data['diagram_title']);
        $this->assertNull($notification->read_at);

        $this->actingAs($collaborator)->getJson('/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'message' => __('messages.notification_collaborator_removed', [
                    'diagram' => $diagram->title,
                ]),
            ]);

        $response = $this->get(
            "/notifications/{$notification->id}/read"
        );
        $response->assertRedirect(route('diagrams.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_the_requester_is_not_notified_only_the_owner_is(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ])->assertCreated();

        $this->assertCount(0, $requester->fresh()->notifications);
    }

    public function test_approving_an_access_request_notifies_the_requester_and_links_to_the_diagram(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->requestAccess($requester, $diagram);

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}")
            ->assertOk()
            ->assertJsonPath('status', Collaborator::STATUS_APPROVED);

        $notification = $requester->fresh()->notifications->sole();

        $this->assertSame(DiagramAccessRequestResolved::class, $notification->type);
        $this->assertSame($diagram->id, $notification->data['diagram_id']);
        $this->assertTrue($notification->data['approved']);

        $this->actingAs($requester)->getJson('/notifications')
            ->assertJsonPath('0.message', __('messages.notification_access_request_approved', [
                'diagram' => $diagram->title,
            ]));

        $this->actingAs($requester)->get("/notifications/{$notification->id}/read")
            ->assertRedirect(route('diagrams.show', $diagram->slug).'?open-panel=collaborators');
    }

    public function test_refusing_an_access_request_notifies_the_requester_and_links_to_the_diagram(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->requestAccess($requester, $diagram);

        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}")
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $notification = $requester->fresh()->notifications->sole();

        $this->assertSame(DiagramAccessRequestResolved::class, $notification->type);
        $this->assertSame($diagram->id, $notification->data['diagram_id']);
        $this->assertFalse($notification->data['approved']);

        $this->actingAs($requester)->getJson('/notifications')
            ->assertJsonPath('0.message', __('messages.notification_access_request_refused', [
                'diagram' => $diagram->title,
            ]));

        $this->actingAs($requester)->get("/notifications/{$notification->id}/read")
            ->assertRedirect(route('diagrams.show', $diagram->slug).'?open-panel=collaborators');
    }

    public function test_removing_an_approved_collaborator_does_not_send_an_access_request_refusal(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $collaborator->id,
        ])->assertCreated();

        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}")
            ->assertOk();

        $this->assertSame(
            [DiagramCollaboratorInvited::class, DiagramCollaboratorRemoved::class],
            $collaborator->fresh()->notifications->pluck('type')->sort()->values()->all()
        );
        $this->assertNull(
            $collaborator->fresh()->notifications->firstWhere('type', DiagramAccessRequestResolved::class)
        );
    }

    public function test_unread_count_endpoint_reports_the_authenticated_users_unread_notifications(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner)->getJson('/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->actingAs($requester)->getJson('/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 0]);
    }

    public function test_clicking_a_notification_marks_it_as_read_and_redirects_to_the_diagram(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ]);

        $notification = $owner->fresh()->notifications->first();

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($owner)->get("/notifications/{$notification->id}/read");

        $response->assertRedirect(route('diagrams.show', $diagram->slug).'?open-panel=collaborators');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_clicking_a_lock_request_notification_marks_it_read_and_opens_the_diagram(): void
    {
        $holder = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);
        $this->actingAs($holder)->get("/diagrams/{$diagram->slug}");
        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/lock")->assertOk();

        $notification = $holder->notifications()->where('type', DiagramLockRequested::class)->firstOrFail();
        $listed = $this->actingAs($holder)->getJson('/notifications')->json();

        $this->assertSame(route('notifications.read', $notification->id), $listed[0]['url']);
        $this->assertSame(route('diagrams.show', $diagram->slug), $listed[0]['opens']);
        $this->actingAs($holder)->get("/notifications/{$notification->id}/read")
            ->assertRedirect(route('diagrams.show', $diagram->slug));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_every_unread_notification_for_the_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $requesterOne = User::factory()->create();
        $requesterTwo = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($requesterOne)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requesterOne->id,
        ]);
        $this->actingAs($requesterTwo)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requesterTwo->id,
        ]);

        $this->assertSame(2, $owner->fresh()->unreadNotifications->count());

        $this->actingAs($owner)->postJson('/notifications/read-all')
            ->assertOk()
            ->assertJson(['marked' => true]);

        $this->assertSame(0, $owner->fresh()->unreadNotifications->count());
    }

    public function test_adding_a_member_notifies_that_user(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => $newMember->email,
        ])->assertRedirect();

        $this->assertCount(1, $newMember->fresh()->notifications);

        $notification = $newMember->fresh()->notifications->first();
        $this->assertSame(WorkspaceMemberAdded::class, $notification->type);
        $this->assertSame($workspace->id, $notification->data['workspace_id']);
    }

    public function test_removing_a_member_notifies_that_user(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertRedirect();

        $this->assertCount(1, $member->fresh()->notifications);

        $notification = $member->fresh()->notifications->first();
        $this->assertSame(WorkspaceMemberRemoved::class, $notification->type);
        $this->assertSame($workspace->id, $notification->data['workspace_id']);
    }

    public function test_transferring_workspace_ownership_notifies_the_new_owner(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $newOwner->id,
        ])->assertRedirect();

        $this->assertCount(1, $newOwner->fresh()->notifications);

        $notification = $newOwner->fresh()->notifications->first();
        $this->assertSame(WorkspaceOwnershipTransferred::class, $notification->type);
        $this->assertSame($workspace->id, $notification->data['workspace_id']);
    }

    public function test_a_user_cannot_mark_or_view_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ]);

        $notification = $owner->fresh()->notifications->first();

        $this->actingAs($stranger)->get("/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_users_notification_list_only_shows_their_own_notifications(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner)->getJson('/notifications')
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($requester)->getJson('/notifications')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_history_screen_lists_the_users_notifications_newest_first_marking_read_state(): void
    {
        [$owner, $diagram] = $this->ownerWithDiagram();
        $requesterOne = User::factory()->create();
        $requesterTwo = User::factory()->create();

        $this->requestAccess($requesterOne, $diagram);
        $this->requestAccess($requesterTwo, $diagram);

        $notifications = $owner->fresh()->notifications;
        $older = $notifications->last();
        $newer = $notifications->first();
        $older->markAsRead();

        $response = $this->actingAs($owner)->get('/notifications/history');

        $response->assertOk();
        $response->assertViewIs('notifications.history');

        $items = $response->viewData('notifications')->items();

        $this->assertSame($newer->id, $items[0]['id']);
        $this->assertSame($older->id, $items[1]['id']);
        $this->assertFalse($items[0]['read']);
        $this->assertTrue($items[1]['read']);
        $this->assertSame(route('notifications.read', $newer->id), $items[0]['url']);
    }

    public function test_history_screen_is_paginated(): void
    {
        [$owner, $diagram] = $this->ownerWithDiagram();
        $requester = User::factory()->create();

        collect(range(1, 25))->each(
            fn () => $owner->notify(new DiagramAccessRequested($diagram, $requester))
        );

        $response = $this->actingAs($owner)->get('/notifications/history');

        $paginator = $response->viewData('notifications');

        $this->assertCount(20, $paginator->items());
        $this->assertSame(25, $paginator->total());
        $this->assertTrue($paginator->hasMorePages());
    }

    public function test_history_screen_only_shows_the_authenticated_users_own_notifications(): void
    {
        [$owner, $diagram] = $this->ownerWithDiagram();
        $requester = User::factory()->create();
        $stranger = User::factory()->create();

        $this->requestAccess($requester, $diagram);

        $ownerItems = $this->actingAs($owner)->get('/notifications/history')
            ->viewData('notifications')->items();
        $this->assertCount(1, $ownerItems);

        $strangerItems = $this->actingAs($stranger)->get('/notifications/history')
            ->viewData('notifications')->items();
        $this->assertCount(0, $strangerItems);
    }

    /**
     * @return array{0: User, 1: Diagram}
     */
    private function ownerWithDiagram(): array
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        return [$owner, $diagram];
    }

    private function requestAccess(User $requester, Diagram $diagram): void
    {
        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ]);
    }
}
