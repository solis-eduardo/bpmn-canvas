<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use App\Notifications\DiagramAccessRequested;
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
            ->assertOk()
            ->assertSee($diagram->title);

        $this->post("/diagrams/{$diagram->slug}/collaborators?discover=Requestable", ['user_id' => $requester->id])
            ->assertRedirect(route('diagrams.index', ['discover' => 'Requestable']));
        $this->post("/diagrams/{$diagram->slug}/collaborators?discover=Requestable", ['user_id' => $requester->id])
            ->assertRedirect(route('diagrams.index', ['discover' => 'Requestable']));
        $this->get('/diagrams?discover=Requestable')
            ->assertOk()
            ->assertSee(__('messages.access_request_pending'))
            ->assertDontSee('name="user_id" value="'.$requester->id.'"', false);

        $this->assertDatabaseCount('diagram_collaborators', 1);
        $this->assertDatabaseHas('diagram_collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => DiagramCollaborator::STATUS_REQUESTED,
        ]);
        $this->assertCount(1, $owner->fresh()->notifications);

        $this->get("/diagrams/{$diagram->slug}")->assertForbidden();
        $this->getJson("/diagrams/{$diagram->slug}/content")->assertForbidden();
    }

    public function test_a_direct_invite_does_not_notify_anyone(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $target->id,
        ])->assertCreated();

        $this->assertCount(0, $owner->fresh()->notifications);
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
