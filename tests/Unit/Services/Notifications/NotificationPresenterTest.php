<?php

namespace Tests\Unit\Services\Notifications;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DiagramAccessRequested;
use App\Notifications\DiagramAccessRequestResolved;
use App\Notifications\DiagramCollaboratorInvited;
use App\Notifications\DiagramCollaboratorRemoved;
use App\Notifications\DiagramOwnershipTransferred;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use App\Notifications\WorkspaceOwnershipTransferred;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationPresenterTest extends TestCase
{
    use RefreshDatabase;

    private function stored(object $notification, User $to): DatabaseNotification
    {
        return new DatabaseNotification([
            'type' => $notification::class,
            'data' => $notification->toArray($to),
        ]);
    }

    public function test_describes_every_known_notification_type(): void
    {
        $diagram = Diagram::factory()->create(['title' => 'Onboarding']);
        $workspace = Workspace::factory()->create(['name' => 'Finance']);
        $user = User::factory()->create(['name' => 'Ana']);
        $presenter = new NotificationPresenter;

        $expected = [
            [new DiagramAccessRequested($diagram, $user), ['Ana', 'Onboarding']],
            [new DiagramAccessRequestResolved($diagram, approved: true), ['Onboarding']],
            [new DiagramAccessRequestResolved($diagram, approved: false), ['Onboarding']],
            [new DiagramCollaboratorInvited($diagram), ['Onboarding']],
            [new DiagramCollaboratorRemoved($diagram), ['Onboarding']],
            [new DiagramOwnershipTransferred($diagram), ['Onboarding']],
            [new WorkspaceMemberAdded($workspace), ['Finance']],
            [new WorkspaceMemberRemoved($workspace), ['Finance']],
            [new WorkspaceOwnershipTransferred($workspace), ['Finance']],
        ];

        foreach ($expected as [$notification, $fragments]) {
            $message = $presenter->messageFor($this->stored($notification, $user));

            $this->assertNotSame('', $message, $notification::class);
            $this->assertStringNotContainsString('messages.', $message, $notification::class);
            foreach ($fragments as $fragment) {
                $this->assertStringContainsString($fragment, $message, $notification::class);
            }
        }
    }

    public function test_approved_and_refused_requests_read_differently(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        $presenter = new NotificationPresenter;

        $this->assertNotSame(
            $presenter->messageFor($this->stored(new DiagramAccessRequestResolved($diagram, approved: true), $user)),
            $presenter->messageFor($this->stored(new DiagramAccessRequestResolved($diagram, approved: false), $user)),
        );
    }

    public function test_an_unknown_type_has_no_message(): void
    {
        $notification = new DatabaseNotification(['type' => 'App\\Notifications\\Gone', 'data' => []]);

        $this->assertSame('', (new NotificationPresenter)->messageFor($notification));
    }
}
