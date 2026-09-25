<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workspace creation and membership visibility (CONTEXT.md, "Workspace",
 * "Member", "Workspace Owner"; ADR-0009; issue #26). Management of an
 * existing Workspace's Members/name is covered by WorkspaceMemberTest.
 */
class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_user_can_create_a_workspace_and_becomes_its_owner_and_a_member(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/workspaces', [
            'name' => 'Finance Team',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workspaces', [
            'name' => 'Finance Team',
            'owner_id' => $user->id,
        ]);

        $workspace = Workspace::where('name', 'Finance Team')->firstOrFail();

        $this->assertTrue($workspace->isOwnedBy($user));
        $this->assertTrue($workspace->hasMember($user));
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_users_see_only_the_workspaces_they_are_a_member_of(): void
    {
        $user = User::factory()->create();
        $ownWorkspace = Workspace::factory()->for($user, 'owner')->create(['name' => 'Mine']);
        $otherWorkspace = Workspace::factory()->create(['name' => 'Not Mine']);

        $response = $this->actingAs($user)->get('/workspaces');

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('Not Mine');

        // Sanity check: the other Workspace does exist, the User just isn't
        // a Member of it.
        $this->assertFalse($otherWorkspace->hasMember($user));
    }

    public function test_a_member_of_a_workspace_they_dont_own_sees_it_in_their_list_too(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Shared Space']);
        $workspace->addMember($member);

        $this->actingAs($member)->get('/workspaces')
            ->assertOk()
            ->assertSee('Shared Space');
    }

    public function test_workspaces_show_uses_context_terminology(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Ops']);

        $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}");

        $response->assertOk();
        $response->assertSee('Ops');
        $response->assertSeeText(__('messages.members'));
    }
}
