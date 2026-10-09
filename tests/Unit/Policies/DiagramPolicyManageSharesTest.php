<?php

namespace Tests\Unit\Policies;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\DiagramPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramPolicyManageSharesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_shares_is_for_the_diagram_owner_and_approved_collaborators_only(): void
    {
        $diagram = Diagram::factory()->create();
        $workspace = Workspace::factory()->create();
        $diagram->update(['workspace_id' => $workspace->id]);
        $policy = new DiagramPolicy;

        $approved = User::factory()->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $approved->id, 'status' => Collaborator::STATUS_APPROVED]);
        $pending = User::factory()->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $pending->id, 'status' => Collaborator::STATUS_REQUESTED]);
        $member = User::factory()->create();
        $workspace->members()->create(['user_id' => $member->id]);

        $this->assertTrue($policy->manageShares($diagram->owner, $diagram), 'owner');
        $this->assertTrue($policy->manageShares($approved, $diagram), 'approved collaborator');
        $this->assertFalse($policy->manageShares($pending, $diagram), 'pending collaborator');
        $this->assertFalse($policy->manageShares($member, $diagram), 'workspace member');
        $this->assertFalse($policy->manageShares(User::factory()->create(['is_admin' => true]), $diagram), 'administrator');
        $this->assertFalse($policy->manageShares(User::factory()->create(), $diagram), 'unrelated user');
    }
}
