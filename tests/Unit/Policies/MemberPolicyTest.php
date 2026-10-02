<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Policies\MemberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_is_for_the_workspace_owner_and_administrators(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->addMember($workspace, $member);
        $policy = new MemberPolicy;

        $this->assertTrue($policy->add($workspace->owner, $workspace));
        $this->assertTrue($policy->add(User::factory()->create(['is_admin' => true]), $workspace));
        $this->assertFalse($policy->add($member, $workspace));
    }

    public function test_remove_is_for_managers_but_never_removes_the_workspace_owner(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $policy = new MemberPolicy;

        $this->assertTrue($policy->remove($workspace->owner, $workspace, $member));
        $this->assertTrue($policy->remove($admin, $workspace, $member));
        $this->assertFalse($policy->remove($member, $workspace, $member));
        $this->assertFalse($policy->remove($workspace->owner, $workspace, $workspace->owner));
        $this->assertFalse($policy->remove($admin, $workspace, $workspace->owner));
    }

    public function test_anyone_but_the_workspace_owner_can_leave(): void
    {
        $workspace = Workspace::factory()->create();
        $policy = new MemberPolicy;

        $this->assertTrue($policy->leave(User::factory()->create(), $workspace));
        $this->assertFalse($policy->leave($workspace->owner, $workspace));
    }
}
