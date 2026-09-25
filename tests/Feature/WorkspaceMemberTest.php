<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Managing a Workspace (renaming, adding/removing Members) is Workspace
 * Owner or Administrator only (CONTEXT.md, "Workspace Owner",
 * "Administrator"; ADR-0009; issue #26). A plain Member can't do either.
 */
class WorkspaceMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_workspace_owner_can_rename_the_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Old Name']);

        $response = $this->actingAs($owner)->put("/workspaces/{$workspace->id}", [
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'New Name']);
    }

    public function test_an_administrator_can_rename_any_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->put("/workspaces/{$workspace->id}", [
            'name' => 'Admin Renamed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Admin Renamed']);
    }

    public function test_a_plain_member_cannot_rename_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Old Name']);
        $workspace->addMember($member);

        $response = $this->actingAs($member)->put("/workspaces/{$workspace->id}", [
            'name' => 'Should Not Happen',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Old Name']);
    }

    public function test_the_workspace_owner_can_add_a_member_by_email(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => $target->email,
        ]);

        $response->assertRedirect();
        $this->assertTrue($workspace->hasMember($target));
    }

    public function test_an_administrator_can_add_a_member_to_any_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $response = $this->actingAs($admin)->post("/workspaces/{$workspace->id}/members", [
            'email' => $target->email,
        ]);

        $response->assertRedirect();
        $this->assertTrue($workspace->hasMember($target));
    }

    public function test_a_plain_member_cannot_add_another_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $target = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();
        $workspace->addMember($member);

        $response = $this->actingAs($member)->post("/workspaces/{$workspace->id}/members", [
            'email' => $target->email,
        ]);

        $response->assertForbidden();
        $this->assertFalse($workspace->hasMember($target));
    }

    public function test_adding_a_member_by_an_email_with_no_matching_user_fails_gracefully(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", [
            'email' => 'nobody@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_the_workspace_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();
        $workspace->addMember($member);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertRedirect();
        $this->assertFalse($workspace->hasMember($member));
    }

    public function test_an_administrator_can_remove_a_member_from_any_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();
        $workspace->addMember($member);

        $response = $this->actingAs($admin)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertRedirect();
        $this->assertFalse($workspace->hasMember($member));
    }

    public function test_a_plain_member_cannot_remove_another_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $anotherMember = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();
        $workspace->addMember($member);
        $workspace->addMember($anotherMember);

        $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}/members/{$anotherMember->id}");

        $response->assertForbidden();
        $this->assertTrue($workspace->hasMember($anotherMember));
    }

    public function test_the_workspace_owner_cannot_be_removed_as_a_member(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$owner->id}");

        $response->assertForbidden();
        $this->assertTrue($workspace->hasMember($owner));
    }

    public function test_a_non_member_cannot_view_a_workspace_they_dont_belong_to(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $this->actingAs($stranger)->get("/workspaces/{$workspace->id}")
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_a_workspace_they_are_not_a_member_of(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $this->actingAs($admin)->get("/workspaces/{$workspace->id}")
            ->assertOk();
    }
}
