<?php

namespace Tests\Unit\Policies;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ForkPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForkPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_who_may_open_the_diagram_can_fork_it(): void
    {
        $diagram = Diagram::factory()->create();
        $policy = new ForkPolicy;

        $this->assertTrue($policy->create($diagram->owner, $diagram));
        $this->assertTrue($policy->create(User::factory()->create(['is_admin' => true]), $diagram));
        $this->assertFalse($policy->create(User::factory()->create(), $diagram));
    }

    public function test_placing_the_fork_in_a_workspace_requires_membership(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create();
        $owner = $diagram->owner;
        $policy = new ForkPolicy;

        $this->assertFalse($policy->create($owner, $diagram, $workspace));

        $this->addMember($workspace, $owner);

        $this->assertTrue($policy->create($owner, $diagram, $workspace));
    }
}
