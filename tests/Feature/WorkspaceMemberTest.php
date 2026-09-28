<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsEmailEnumerationPrevention;

class WorkspaceMemberTest extends TestCase
{
    use RefreshDatabase;
    use TestsEmailEnumerationPrevention;

    public function test_workspace_owner_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => $newMember->email,
        ]);

        $response->assertRedirect();
        $this->assertTrue($workspace->fresh()->hasMember($newMember));
    }

    public function test_workspace_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertRedirect();
        $this->assertFalse($workspace->fresh()->hasMember($member));
    }

    public function test_administrator_can_add_a_member_to_any_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($admin)->post("/workspaces/{$workspace->id}/members", [
            'email' => $newMember->email,
        ]);

        $response->assertRedirect();
        $this->assertTrue($workspace->fresh()->hasMember($newMember));
    }

    public function test_administrator_can_remove_a_member_from_any_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($admin)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertRedirect();
        $this->assertFalse($workspace->fresh()->hasMember($member));
    }

    public function test_plain_member_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $someoneElse = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($member)->post("/workspaces/{$workspace->id}/members", [
            'email' => $someoneElse->email,
        ]);

        $response->assertForbidden();
        $this->assertFalse($workspace->fresh()->hasMember($someoneElse));
    }

    public function test_plain_member_cannot_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $anotherMember = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);
        $workspace->addMember($anotherMember);

        $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}/members/{$anotherMember->id}");

        $response->assertForbidden();
        $this->assertTrue($workspace->fresh()->hasMember($anotherMember));
    }

    public function test_unrelated_user_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $target = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($stranger)->post("/workspaces/{$workspace->id}/members", [
            'email' => $target->email,
        ]);

        $response->assertForbidden();
    }

    public function test_removing_the_workspace_owner_is_refused(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$owner->id}");

        $response->assertStatus(422);
        $this->assertTrue($workspace->fresh()->hasMember($owner));
    }

    public function test_a_plain_member_can_leave_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}/leave");

        $response->assertRedirect();
        $this->assertFalse($workspace->fresh()->hasMember($member));
    }

    public function test_the_workspace_owner_is_refused_when_trying_to_leave_without_transferring_first(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/leave");

        $response->assertStatus(422);
        $this->assertTrue($workspace->fresh()->hasMember($owner));
    }

    public function test_the_former_owner_can_leave_after_transferring_ownership(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/leave");

        $response->assertRedirect();
        $this->assertFalse($workspace->fresh()->hasMember($owner));
    }

    public function test_adding_by_email_returns_identical_response_for_existing_and_non_existing_email(): void
    {
        $owner = User::factory()->create();
        $existingUser = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $responseWithExistingEmail = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => $existingUser->email,
        ]);

        $responseWithNonExistingEmail = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertEmailEnumerationPreventedWithRedirect($responseWithExistingEmail, $responseWithNonExistingEmail);
    }
}
