<?php

namespace Tests\Unit\Policies;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Policies\CollaboratorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaboratorPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_and_revoke_are_for_the_diagram_owner_and_administrators(): void
    {
        $diagram = Diagram::factory()->create();
        $policy = new CollaboratorPolicy;
        $admin = User::factory()->create(['is_admin' => true]);
        $stranger = User::factory()->create();
        $collaborator = User::factory()->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $collaborator->id, 'status' => Collaborator::STATUS_APPROVED]);

        foreach (['grant', 'revoke'] as $ability) {
            $this->assertTrue($policy->$ability($diagram->owner, $diagram), $ability);
            $this->assertTrue($policy->$ability($admin, $diagram), $ability);
            $this->assertFalse($policy->$ability($collaborator, $diagram), $ability);
            $this->assertFalse($policy->$ability($stranger, $diagram), $ability);
        }
    }

    public function test_only_those_without_access_can_request_it(): void
    {
        $diagram = Diagram::factory()->create();
        $policy = new CollaboratorPolicy;
        $stranger = User::factory()->create();
        $pending = User::factory()->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $pending->id, 'status' => Collaborator::STATUS_REQUESTED]);
        $approved = User::factory()->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $approved->id, 'status' => Collaborator::STATUS_APPROVED]);

        $this->assertTrue($policy->requestAccess($stranger, $diagram));
        $this->assertTrue($policy->requestAccess($pending, $diagram));
        $this->assertFalse($policy->requestAccess($approved, $diagram));
        $this->assertFalse($policy->requestAccess($diagram->owner, $diagram));
        $this->assertFalse($policy->requestAccess(User::factory()->create(['is_admin' => true]), $diagram));
    }

    public function test_inviting_is_open_to_managers_for_anyone_and_to_others_only_for_themselves(): void
    {
        $diagram = Diagram::factory()->create();
        $policy = new CollaboratorPolicy;
        $stranger = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue($policy->invite($diagram->owner, $diagram, $other));
        $this->assertTrue($policy->invite($stranger, $diagram, $stranger));
        $this->assertFalse($policy->invite($stranger, $diagram, $other));
    }

    public function test_an_email_that_matched_nobody_can_only_be_tried_by_those_who_can_grant(): void
    {
        $diagram = Diagram::factory()->create();
        $policy = new CollaboratorPolicy;

        $this->assertTrue($policy->invite($diagram->owner, $diagram, null));
        $this->assertFalse($policy->invite(User::factory()->create(), $diagram, null));
    }
}
