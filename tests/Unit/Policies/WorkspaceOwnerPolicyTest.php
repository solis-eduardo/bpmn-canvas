<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Policies\WorkspaceOwnerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceOwnerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_is_for_the_workspace_owner_and_administrators(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $this->addMember($workspace, $member);
        $policy = new WorkspaceOwnerPolicy;

        $this->assertTrue($policy->transfer($workspace->owner, $workspace));
        $this->assertTrue($policy->transfer(User::factory()->create(['is_admin' => true]), $workspace));
        $this->assertFalse($policy->transfer($member, $workspace));
    }
}
