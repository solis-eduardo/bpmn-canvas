<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_create_a_workspace_and_becomes_its_owner_and_a_member(): void
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
    }

    public function test_guest_cannot_create_a_workspace(): void
    {
        $response = $this->post('/workspaces', ['name' => 'No Login']);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('workspaces', ['name' => 'No Login']);
    }

    public function test_user_sees_only_the_workspaces_they_are_a_member_of(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();

        $mine = Workspace::factory()->create(['owner_id' => $user->id]);
        $mine->addMember($user);

        $notMine = Workspace::factory()->create(['owner_id' => $otherOwner->id]);
        $notMine->addMember($otherOwner);

        $response = $this->actingAs($user)->get('/workspaces');

        $response->assertOk();
        $response->assertSee($mine->name);
        $response->assertDontSee($notMine->name);
        $response->assertDontSee(route('workspaces.keycloak-group.update', $mine));
    }

    public function test_administrator_viewing_a_workspace_they_are_not_a_member_of_is_not_offered_a_leave_button(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($admin)->get('/workspaces');

        $response->assertOk();
        $response->assertDontSee(__('messages.leave_workspace'));
    }

    public function test_administrator_sees_every_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($admin)->get('/workspaces');

        $response->assertOk();
        $response->assertSee($workspace->name);
        $response->assertSee(route('workspaces.keycloak-group.update', $workspace));
    }

    public function test_workspace_owner_can_rename_the_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($owner)->put("/workspaces/{$workspace->id}", [
            'name' => 'Renamed Workspace',
        ]);

        $response->assertRedirect();
        $this->assertSame('Renamed Workspace', $workspace->fresh()->name);
    }

    public function test_administrator_can_rename_any_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($admin)->put("/workspaces/{$workspace->id}", [
            'name' => 'Renamed By Admin',
        ]);

        $response->assertRedirect();
        $this->assertSame('Renamed By Admin', $workspace->fresh()->name);
    }

    public function test_plain_member_cannot_rename_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($member)->put("/workspaces/{$workspace->id}", [
            'name' => 'Should Not Happen',
        ]);

        $response->assertForbidden();
        $this->assertSame($workspace->name, $workspace->fresh()->name);
    }

    public function test_only_an_administrator_can_link_a_workspace_to_a_keycloak_group(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/keycloak-group", [
            'keycloak_group' => '/finance',
        ])->assertForbidden();

        $this->assertNull($workspace->fresh()->keycloak_group);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put("/workspaces/{$workspace->id}/keycloak-group", [
            'keycloak_group' => '/finance',
        ])->assertRedirect(route('workspaces.index'));

        $this->assertSame('/finance', $workspace->fresh()->keycloak_group);
    }

    public function test_an_administrator_can_unlink_a_workspace_from_a_keycloak_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'keycloak_group' => '/finance',
        ]);

        $this->actingAs($admin)->put("/workspaces/{$workspace->id}/keycloak-group", [
            'keycloak_group' => '',
        ])->assertRedirect(route('workspaces.index'));

        $this->assertNull($workspace->fresh()->keycloak_group);
    }

    public function test_unrelated_user_cannot_rename_the_workspace(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($stranger)->put("/workspaces/{$workspace->id}", [
            'name' => 'Should Not Happen',
        ]);

        $response->assertForbidden();
    }

    public function test_workspace_owner_can_delete_an_empty_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    public function test_administrator_can_delete_any_empty_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($admin)->delete("/workspaces/{$workspace->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    public function test_plain_member_cannot_delete_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_deleting_a_workspace_that_holds_diagrams_is_refused(): void
    {
        Storage::fake('diagrams');

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_deleting_a_workspace_is_allowed_once_its_diagrams_are_gone(): void
    {
        Storage::fake('diagrams');

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['workspace_id' => $workspace->id]);
        $diagram->update(['workspace_id' => null]);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }
}
