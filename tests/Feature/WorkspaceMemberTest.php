<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => $newMember->email,
        ]);

        $response->assertRedirect();
        $this->assertTrue($this->isMember($workspace, $newMember));
    }

    public function test_syncing_adds_and_removes_members_to_match_the_submitted_set(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $kept = User::factory()->create();
        $dropped = User::factory()->create();
        $added = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $kept);
        $this->addMember($workspace, $dropped);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/members", [
            'members' => [$owner->id, $kept->id, $added->id],
        ])->assertRedirect(route('workspaces.edit', $workspace));

        $this->assertTrue($this->isMember($workspace, $kept));
        $this->assertTrue($this->isMember($workspace, $added));
        $this->assertFalse($this->isMember($workspace, $dropped));
        Notification::assertSentTo($added, WorkspaceMemberAdded::class);
        Notification::assertSentTo($dropped, WorkspaceMemberRemoved::class);
        Notification::assertNotSentTo($kept, WorkspaceMemberAdded::class);
    }

    public function test_syncing_without_the_workspace_owner_is_refused_with_an_error_under_the_field(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/members", ['members' => [$member->id]])
            ->assertSessionHasErrors('members');

        $this->assertTrue($this->isMember($workspace, $owner));
    }

    public function test_syncing_members_is_refused_to_a_plain_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $this->actingAs($member)->put("/workspaces/{$workspace->id}/members", ['members' => [$owner->id]])
            ->assertForbidden();

        $this->assertTrue($this->isMember($workspace, $member));
    }

    public function test_the_edit_screen_shows_the_members_as_selected_chips_of_one_multi_select(): void
    {
        $owner = User::factory()->create(['name' => 'Olivia Owner']);
        $member = User::factory()->create(['name' => 'Mark Member']);
        $other = User::factory()->create(['name' => 'Otto Outsider']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($owner)->get(route('workspaces.edit', $workspace));

        $response->assertOk();
        $response->assertSee('name="members[]"', false);
        $response->assertSee('<option value="'.$member->id.'" selected>', false);
        $response->assertSee('<option value="'.$other->id.'">', false);
        $response->assertSee(route('workspaces.members.sync', $workspace), false);
    }

    public function test_workspace_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertRedirect();
        $this->assertFalse($this->isMember($workspace, $member));
    }

    public function test_administrator_can_add_a_member_to_any_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($admin)->post("/workspaces/{$workspace->id}/members", [
            'email' => $newMember->email,
        ]);

        $response->assertRedirect();
        $this->assertTrue($this->isMember($workspace, $newMember));
    }

    public function test_administrator_can_remove_a_member_from_any_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($admin)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertRedirect();
        $this->assertFalse($this->isMember($workspace, $member));
    }

    public function test_plain_member_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $someoneElse = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($member)->post("/workspaces/{$workspace->id}/members", [
            'email' => $someoneElse->email,
        ]);

        $response->assertForbidden();
        $this->assertFalse($this->isMember($workspace, $someoneElse));
    }

    public function test_plain_member_cannot_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $anotherMember = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);
        $this->addMember($workspace, $anotherMember);

        $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}/members/{$anotherMember->id}");

        $response->assertForbidden();
        $this->assertTrue($this->isMember($workspace, $anotherMember));
    }

    public function test_unrelated_user_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $target = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($stranger)->post("/workspaces/{$workspace->id}/members", [
            'email' => $target->email,
        ]);

        $response->assertForbidden();
    }

    public function test_removing_the_workspace_owner_is_refused(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$owner->id}");

        $response->assertForbidden();
        $this->assertTrue($this->isMember($workspace, $owner));
    }

    public function test_a_plain_member_can_leave_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}/leave");

        $response->assertRedirect();
        $this->assertFalse($this->isMember($workspace, $member));
    }

    public function test_the_workspace_owner_is_refused_when_trying_to_leave_without_transferring_first(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/leave");

        $response->assertForbidden();
        $this->assertTrue($this->isMember($workspace, $owner));
    }

    public function test_the_former_owner_can_leave_after_transferring_ownership(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/leave");

        $response->assertRedirect();
        $this->assertFalse($this->isMember($workspace, $owner));
    }

    public function test_adding_by_email_returns_identical_response_for_existing_and_non_existing_email(): void
    {
        $owner = User::factory()->create();
        $existingUser = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $responseWithExistingEmail = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => $existingUser->email,
        ]);

        $responseWithNonExistingEmail = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertEmailEnumerationPreventedWithRedirect($responseWithExistingEmail, $responseWithNonExistingEmail);
    }
}
