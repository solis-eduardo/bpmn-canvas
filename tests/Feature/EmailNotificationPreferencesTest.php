<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Notifications\DiagramAccessRequested;
use App\Notifications\QueuedMailChannel;
use App\Notifications\SendQueuedNotificationEmail;
use App\Notifications\ShareExpiringSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_view_and_update_their_email_notification_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('name="email_notifications_enabled"', false)
            ->assertDontSee('checked', false);

        $this->actingAs($user)->get('/account/notifications')
            ->assertRedirect('/account');

        $this->actingAs($user)->put('/account/notifications', [
            'email_notifications_enabled' => '1',
        ])->assertRedirect('/account');

        $this->assertTrue($user->fresh()->email_notifications_enabled);

        $this->actingAs($user)->put('/account/notifications', [])
            ->assertRedirect('/account');

        $this->assertFalse($user->fresh()->email_notifications_enabled);
    }

    public function test_email_notifications_are_queued_only_for_users_who_opt_in(): void
    {
        Notification::fake();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->create();

        $optedOutUser = User::factory()->create();
        $optedOutUser->notify(new DiagramAccessRequested($diagram, $requester));

        Notification::assertSentTo(
            $optedOutUser,
            DiagramAccessRequested::class,
            fn (DiagramAccessRequested $notification, array $channels): bool => $channels === ['database']
        );

        $optedInUser = User::factory()->create(['email_notifications_enabled' => true]);
        $optedInUser->notify(new DiagramAccessRequested($diagram, $requester));

        Notification::assertSentTo(
            $optedInUser,
            DiagramAccessRequested::class,
            fn (DiagramAccessRequested $notification, array $channels): bool => $channels === ['database', QueuedMailChannel::class]
        );
    }

    public function test_share_expiration_notifications_also_follow_the_email_preference(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email_notifications_enabled' => true]);
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();
        $share = Share::factory()->for($diagram)->create();

        $owner->notify(new ShareExpiringSoon($share));

        Notification::assertSentTo(
            $owner,
            ShareExpiringSoon::class,
            fn (ShareExpiringSoon $notification, array $channels): bool => $channels === ['database', QueuedMailChannel::class]
        );
    }

    public function test_opted_in_email_is_queued_while_the_in_app_notification_is_saved_immediately(): void
    {
        Queue::fake();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->create();
        $owner = User::factory()->create(['email_notifications_enabled' => true]);

        $owner->notify(new DiagramAccessRequested($diagram, $requester));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'type' => DiagramAccessRequested::class,
        ]);
        Queue::assertPushed(SendQueuedNotificationEmail::class);
    }
}
