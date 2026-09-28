<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsEmailEnumerationPrevention;

/**
 * Transferring the Workspace Owner role (CONTEXT.md, "Workspace Owner";
 * ADR-0006; issue #29): the current Workspace Owner or an Administrator
 * hands the Workspace to another User with no acceptance step. The
 * recipient becomes Workspace Owner immediately, and a Member too if they
 * weren't one already.
 */
class WorkspaceOwnershipTransferTest extends TestCase
{
    use RefreshDatabase;
    use TestsEmailEnumerationPrevention;

    public function test_the_workspace_owner_can_transfer_ownership_and_the_recipient_becomes_owner_and_a_member(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertRedirect();

        $workspace->refresh();
        $this->assertTrue($workspace->isOwnedBy($newOwner));
        $this->assertTrue($workspace->hasMember($newOwner));
        // The previous Owner stays a Member.
        $this->assertTrue($workspace->hasMember($owner));
    }

    public function test_transferring_to_an_existing_member_just_promotes_them(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $member->id,
        ]);

        $response->assertRedirect();

        $workspace->refresh();
        $this->assertTrue($workspace->isOwnedBy($member));
        $this->assertTrue($workspace->hasMember($member));
        $this->assertSame(2, $workspace->members()->count(), 'no duplicate Member row is created');
    }

    public function test_an_administrator_can_transfer_ownership_of_someone_elses_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($admin)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertRedirect();

        $workspace->refresh();
        $this->assertTrue($workspace->isOwnedBy($newOwner));
        $this->assertTrue($workspace->hasMember($newOwner));
    }

    public function test_a_plain_member_cannot_transfer_ownership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($member)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertForbidden();

        $workspace->refresh();
        $this->assertTrue($workspace->isOwnedBy($owner));
    }

    public function test_an_unrelated_user_cannot_transfer_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($stranger)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertForbidden();

        $workspace->refresh();
        $this->assertTrue($workspace->isOwnedBy($owner));
    }

    public function test_transferring_by_email_returns_identical_response_for_existing_and_non_existing_email(): void
    {
        $owner = User::factory()->create();
        $existingUser = User::factory()->create();
        $workspace1 = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace1->addMember($owner);
        $workspace2 = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace2->addMember($owner);

        $responseWithExistingEmail = $this->actingAs($owner)->put("/workspaces/{$workspace1->id}/owner", [
            'email' => $existingUser->email,
        ]);

        $responseWithNonExistingEmail = $this->actingAs($owner)->put("/workspaces/{$workspace2->id}/owner", [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertEmailEnumerationPreventedWithRedirect($responseWithExistingEmail, $responseWithNonExistingEmail);
    }
}
