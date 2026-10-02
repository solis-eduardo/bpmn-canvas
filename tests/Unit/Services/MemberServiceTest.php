<?php

namespace Tests\Unit\Services;

use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MemberServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->service = app(MemberService::class);
    }

    public function test_add_creates_a_manual_member_and_tells_them(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        $member = $this->service->add($workspace, $user);

        $this->assertSame(Member::ORIGIN_MANUAL, $member->origin);
        Notification::assertSentTo($user, WorkspaceMemberAdded::class);
    }

    public function test_adding_an_existing_member_does_not_notify_again(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->service->add($workspace, $user);

        $this->service->add($workspace, $user);

        Notification::assertSentToTimes($user, WorkspaceMemberAdded::class, 1);
    }

    public function test_remove_drops_the_member_and_tells_them(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->addMember($workspace, $user);

        $this->assertTrue($this->service->remove($workspace, $user));

        $this->assertFalse($this->isMember($workspace, $user));
        Notification::assertSentTo($user, WorkspaceMemberRemoved::class);
    }

    public function test_removing_a_non_member_notifies_no_one(): void
    {
        $this->assertFalse($this->service->remove(Workspace::factory()->create(), User::factory()->create()));

        Notification::assertNothingSent();
    }

    public function test_leave_removes_the_user_themselves(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $this->addMember($workspace, $user);

        $this->assertTrue($this->service->leave($workspace, $user));

        $this->assertFalse($this->isMember($workspace, $user));
    }

    public function test_sync_adds_group_members_for_linked_workspaces_and_tells_them(): void
    {
        $linked = Workspace::factory()->create(['keycloak_group' => 'finance']);
        $other = Workspace::factory()->create(['keycloak_group' => 'hr']);
        $user = User::factory()->create();

        $this->service->syncFromKeycloakGroups($user, ['finance']);

        $this->assertSame(Member::ORIGIN_GROUP, Member::where('workspace_id', $linked->id)->value('origin'));
        $this->assertFalse($this->isMember($other, $user));
        Notification::assertSentToTimes($user, WorkspaceMemberAdded::class, 1);
    }

    public function test_sync_drops_group_memberships_outside_the_groups_but_keeps_manual_ones(): void
    {
        $left = Workspace::factory()->create(['keycloak_group' => 'old']);
        $manual = Workspace::factory()->create(['keycloak_group' => 'old']);
        $user = User::factory()->create();
        $this->addGroupMember($left, $user);
        $this->addMember($manual, $user);

        $this->service->syncFromKeycloakGroups($user, []);

        $this->assertFalse($this->isMember($left, $user));
        $this->assertTrue($this->isMember($manual, $user));
    }

    public function test_sync_does_not_notify_for_existing_memberships(): void
    {
        $workspace = Workspace::factory()->create(['keycloak_group' => 'finance']);
        $user = User::factory()->create();
        $this->addMember($workspace, $user);

        $this->service->syncFromKeycloakGroups($user, ['finance']);

        Notification::assertNothingSent();
        $this->assertSame(Member::ORIGIN_MANUAL, Member::where('workspace_id', $workspace->id)->value('origin'));
    }
}
