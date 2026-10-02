<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceOwnershipTransferred;
use App\Services\WorkspaceOwnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkspaceOwnerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_recipient_becomes_owner_and_member_and_is_notified(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();
        $newOwner = User::factory()->create();

        app(WorkspaceOwnerService::class)->transfer($workspace, $newOwner);

        $this->assertSame($newOwner->id, $workspace->fresh()->owner_id);
        $this->assertTrue($this->isMember($workspace, $newOwner));
        Notification::assertSentTo($newOwner, WorkspaceOwnershipTransferred::class);
    }

    public function test_the_previous_owner_stays_a_member(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();
        $previous = $workspace->owner;
        $this->addMember($workspace, $previous);

        app(WorkspaceOwnerService::class)->transfer($workspace, User::factory()->create());

        $this->assertTrue($this->isMember($workspace, $previous));
    }
}
