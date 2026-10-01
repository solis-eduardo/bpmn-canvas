<?php

namespace Tests\Unit\Policies;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\DiagramWorkspacePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramWorkspacePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_needs_the_diagram_owner_to_be_a_member_of_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create();
        $policy = new DiagramWorkspacePolicy;

        $this->assertFalse($policy->assign($diagram->owner, $diagram, $workspace));

        $this->addMember($workspace, $diagram->owner);

        $this->assertTrue($policy->assign($diagram->owner, $diagram, $workspace));
    }

    public function test_assign_is_not_for_anyone_but_the_diagram_owner(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->addMember($workspace, $other);
        $this->addMember($workspace, $admin);
        $policy = new DiagramWorkspacePolicy;

        $this->assertFalse($policy->assign($other, $diagram, $workspace));
        $this->assertFalse($policy->assign($admin, $diagram, $workspace));
    }

    public function test_remove_is_for_the_diagram_owner_the_workspace_owner_and_administrators(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);
        $policy = new DiagramWorkspacePolicy;

        $this->assertTrue($policy->remove($diagram->owner, $diagram));
        $this->assertTrue($policy->remove($workspace->owner, $diagram));
        $this->assertTrue($policy->remove(User::factory()->create(['is_admin' => true]), $diagram));
        $this->assertFalse($policy->remove(User::factory()->create(), $diagram));
    }

    public function test_a_workspace_owner_has_no_say_over_a_diagram_outside_their_workspace(): void
    {
        $diagram = Diagram::factory()->create();

        $this->assertFalse((new DiagramWorkspacePolicy)->remove(Workspace::factory()->create()->owner, $diagram));
    }
}
