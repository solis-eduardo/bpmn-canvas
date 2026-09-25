<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
