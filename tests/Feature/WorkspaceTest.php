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
        $this->assertTrue($this->isMember($workspace, $user));
    }

    public function test_listing_page_has_no_create_or_rename_form_and_links_to_create_and_edit_pages(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $this->addMember($workspace, $user);

        $response = $this->actingAs($user)->get('/workspaces');

        $response->assertOk();
        $response->assertSee(route('workspaces.create'));
        $response->assertSee(route('workspaces.edit', $workspace));
        $response->assertDontSee('method="POST" action="'.route('workspaces.store').'"', false);
        $response->assertDontSee('type="text" name="name"', false);
    }

    public function test_listing_does_not_link_to_the_edit_page_for_a_workspace_the_user_cannot_manage(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $user);

        $this->actingAs($user)->get('/workspaces')
            ->assertDontSee(route('workspaces.edit', $workspace));
    }

    public function test_create_page_renders_the_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/workspaces/create');

        $response->assertOk();
        $response->assertSee('action="'.route('workspaces.store').'"', false);
    }

    public function test_edit_page_renders_the_rename_form_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}/edit");

        $response->assertOk();
        $response->assertSee('action="'.route('workspaces.update', $workspace).'"', false);
        $response->assertSee('Old Name');
    }

    public function test_administrator_can_open_the_edit_page_of_any_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = Workspace::factory()->create();

        $this->actingAs($admin)->get("/workspaces/{$workspace->id}/edit")->assertOk();
    }

    public function test_unrelated_user_cannot_open_the_edit_page(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $this->actingAs($stranger)->get("/workspaces/{$workspace->id}/edit")->assertForbidden();
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
        $this->addMember($mine, $user);

        $notMine = Workspace::factory()->create(['owner_id' => $otherOwner->id]);
        $this->addMember($notMine, $otherOwner);

        $response = $this->actingAs($user)->get('/workspaces');

        $response->assertOk();
        $response->assertSee($mine->name);
        $response->assertDontSee($notMine->name);
    }

    public function test_administrator_viewing_a_workspace_they_are_not_a_member_of_is_not_offered_a_leave_button(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($admin)->get('/workspaces');

        $response->assertOk();
        $response->assertDontSee(__('messages.leave_workspace'));
    }

    public function test_administrator_sees_every_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($admin)->get('/workspaces');

        $response->assertOk();
        $response->assertSee($workspace->name);
    }

    public function test_workspace_owner_can_rename_the_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

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
        $this->addMember($workspace, $owner);

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
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($member)->put("/workspaces/{$workspace->id}", [
            'name' => 'Should Not Happen',
        ]);

        $response->assertForbidden();
        $this->assertSame($workspace->name, $workspace->fresh()->name);
    }

    public function test_only_an_administrator_can_link_a_workspace_to_a_keycloak_group_through_the_workspace_form(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}", [
            'name' => 'Renamed',
            'keycloak_group' => '/finance',
        ])->assertForbidden();

        $this->assertNull($workspace->fresh()->keycloak_group);
        $this->assertNotSame('Renamed', $workspace->fresh()->name);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put("/workspaces/{$workspace->id}", [
            'name' => 'Renamed',
            'keycloak_group' => '/finance',
        ])->assertRedirect(route('workspaces.edit', $workspace));

        $this->assertSame('/finance', $workspace->fresh()->keycloak_group);
        $this->assertSame('Renamed', $workspace->fresh()->name);
    }

    public function test_the_owner_renaming_without_the_keycloak_field_keeps_the_link(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'keycloak_group' => '/finance']);
        $this->addMember($workspace, $owner);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}", ['name' => 'Renamed'])
            ->assertRedirect(route('workspaces.edit', $workspace));

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

        $this->actingAs($admin)->put("/workspaces/{$workspace->id}", [
            'name' => $workspace->name,
            'keycloak_group' => '',
        ])->assertRedirect(route('workspaces.edit', $workspace));

        $this->assertNull($workspace->fresh()->keycloak_group);
    }

    public function test_keycloak_group_is_validated_in_the_workspace_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = Workspace::factory()->create();

        $this->actingAs($admin)->put("/workspaces/{$workspace->id}", [
            'name' => 'Ok',
            'keycloak_group' => str_repeat('x', 256),
        ])->assertSessionHasErrors('keycloak_group');
    }

    public function test_an_administrator_can_set_the_keycloak_group_when_creating_a_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/workspaces', ['name' => 'Linked', 'keycloak_group' => '/finance'])
            ->assertRedirect(route('workspaces.edit', Workspace::where('name', 'Linked')->firstOrFail()));

        $this->assertDatabaseHas('workspaces', ['name' => 'Linked', 'keycloak_group' => '/finance']);
    }

    public function test_a_non_administrator_cannot_set_the_keycloak_group_when_creating_a_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/workspaces', ['name' => 'Linked', 'keycloak_group' => '/finance'])
            ->assertForbidden();

        $this->assertDatabaseMissing('workspaces', ['name' => 'Linked']);
    }

    public function test_the_keycloak_field_is_offered_on_the_create_form_to_administrators_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/workspaces/create')
            ->assertOk()->assertDontSee('name="keycloak_group"', false);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/workspaces/create')
            ->assertOk()->assertSee('name="keycloak_group"', false);
    }

    public function test_unrelated_user_cannot_rename_the_workspace(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($stranger)->put("/workspaces/{$workspace->id}", [
            'name' => 'Should Not Happen',
        ]);

        $response->assertForbidden();
    }

    public function test_workspace_owner_can_delete_an_empty_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    public function test_administrator_can_delete_any_empty_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $response = $this->actingAs($admin)->delete("/workspaces/{$workspace->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    public function test_plain_member_cannot_delete_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_deleting_a_workspace_that_holds_diagrams_is_refused(): void
    {
        Storage::fake('diagrams');

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
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
        $this->addMember($workspace, $owner);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['workspace_id' => $workspace->id]);
        $diagram->update(['workspace_id' => null]);

        $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    public function test_workspace_form_offers_every_user_with_current_members_preselected_and_owner_candidates(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $candidate = User::factory()->create(['name' => 'Candidate Person', 'email' => 'candidate@example.test']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}/edit");

        $response->assertOk();
        $response->assertSee(route('workspaces.members.sync', $workspace));
        $response->assertSee(route('workspaces.owner.transfer', $workspace));
        $response->assertViewHas('memberOptions', function (array $options) use ($candidate, $owner, $member) {
            $ids = collect($options)->pluck('id');

            return $ids->contains($candidate->id) && $ids->contains($owner->id) && $ids->contains($member->id);
        });
        $response->assertViewHas('memberIds', fn ($ids) => $ids->contains($member->id) && ! $ids->contains($candidate->id));
        $response->assertViewHas('ownerCandidates', function (array $options) use ($candidate, $owner, $member) {
            $ids = collect($options)->pluck('id');

            return $ids->contains($candidate->id) && $ids->contains($member->id) && ! $ids->contains($owner->id);
        });
        $response->assertSee('Candidate Person');
        $response->assertSee('candidate@example.test');
        $response->assertDontSee('name="keycloak_group"', false);
        $response->assertDontSee('data-user-select', false);
        $response->assertDontSee('type="search"', false);
        $response->assertSee('data-tag-select', false);
        $response->assertSee('for="members-members"', false);
        $response->assertSee('for="transfer-owner-user_id"', false);
    }

    public function test_only_an_administrator_sees_the_keycloak_field_in_the_main_workspace_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = Workspace::factory()->create();
        $this->addMember($workspace, $workspace->owner);

        $this->actingAs($admin)->get("/workspaces/{$workspace->id}/edit")
            ->assertOk()
            ->assertSee('name="keycloak_group"', false)
            ->assertSee(__('messages.keycloak_group'))
            ->assertDontSee('/keycloak-group', false);
    }

    public function test_listing_is_read_only_for_keycloak_link_members_and_ownership(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'keycloak_group' => '/finance']);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $response = $this->actingAs($admin)->get('/workspaces');

        $response->assertOk()
            ->assertSee(__('messages.keycloak_group'))
            ->assertSee('/finance')
            ->assertDontSee(route('workspaces.members.store', $workspace))
            ->assertDontSee(route('workspaces.owner.transfer', $workspace))
            ->assertDontSee('data-user-select', false);
        $this->assertSame(2, $response->viewData('workspaces')->first()->members_count);
    }

    public function test_a_plain_member_cannot_open_the_workspace_form_or_see_other_users(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        User::factory()->create(['email' => 'hidden@example.test']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $this->actingAs($member)->get("/workspaces/{$workspace->id}/edit")->assertForbidden();
    }

    public function test_adding_or_transferring_to_a_user_id_that_does_not_exist_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", ['user_id' => 999999])
            ->assertSessionHasErrors('user_id');
        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", ['user_id' => 999999])
            ->assertSessionHasErrors('user_id');
    }

    public function test_picking_an_existing_member_or_the_current_owner_by_user_id_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", ['user_id' => $member->id])
            ->assertSessionHasErrors('user_id');
        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", ['user_id' => $owner->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_listing_is_a_table_filterable_by_name_owner_and_member_and_keeps_filters_on_pagination(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['name' => 'Olivia Owner']);
        $member = User::factory()->create(['name' => 'Mark Member']);

        $finance = Workspace::factory()->create(['name' => 'Finance', 'owner_id' => $owner->id]);
        $this->addMember($finance, $owner);
        $this->addMember($finance, $member);
        $other = Workspace::factory()->create(['name' => 'Logistics']);
        $this->addMember($other, $other->owner);

        $this->actingAs($admin)->get('/workspaces')
            ->assertOk()->assertSee('<table', false)->assertSee('Finance')->assertSee('Logistics')
            ->assertDontSee('name="diagram"', false);

        foreach (['name' => 'Fin', 'owner' => $owner->id, 'member' => $member->id] as $key => $value) {
            $this->actingAs($admin)->get('/workspaces?'.$key.'='.$value)
                ->assertOk()->assertSee('Finance')->assertDontSee('Logistics');
        }

        foreach (range(1, 25) as $i) {
            $w = Workspace::factory()->create(['name' => sprintf('Bulk %02d', $i), 'owner_id' => $owner->id]);
            $this->addMember($w, $owner);
        }

        $this->actingAs($admin)->get('/workspaces?owner='.$owner->id)
            ->assertOk()->assertSee('owner='.$owner->id, false)->assertSee('page=2', false);
        $this->actingAs($admin)->get('/workspaces?owner='.$owner->id.'&page=2')
            ->assertOk()->assertSee('Bulk 25')->assertDontSee('Logistics');
    }

    public function test_owner_and_member_filters_are_selects_of_existing_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Pick Me', 'email' => 'pick@example.test']);

        $this->actingAs($admin)->get('/workspaces?owner='.$user->id)
            ->assertOk()
            ->assertSee('<select', false)
            ->assertSee('name="owner"', false)
            ->assertSee('name="member"', false)
            ->assertSee('data-tag-select', false)
            ->assertSee('Pick Me (pick@example.test)')
            ->assertSee('value="'.$user->id.'" selected', false);
    }

    public function test_non_admin_filters_stay_scoped_to_their_workspaces(): void
    {
        $user = User::factory()->create();
        $mine = Workspace::factory()->create(['name' => 'Mine', 'owner_id' => $user->id]);
        $this->addMember($mine, $user);
        $foreign = Workspace::factory()->create(['name' => 'Foreign Mine']);
        $this->addMember($foreign, $foreign->owner);

        $this->actingAs($user)->get('/workspaces?name=Mine')
            ->assertOk()->assertSee('Mine')->assertDontSee('Foreign Mine');
    }

    public function test_save_and_exit_returns_to_the_listing_after_creating_and_updating(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/workspaces', ['name' => 'Exited', 'submit_action' => 'exit'])
            ->assertRedirect(route('workspaces.index'))
            ->assertSessionHas('status');

        $workspace = Workspace::where('name', 'Exited')->firstOrFail();

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}", ['name' => 'Exited again', 'submit_action' => 'exit'])
            ->assertRedirect(route('workspaces.index'));

        $this->assertSame('Exited again', $workspace->fresh()->name);
    }

    public function test_plain_save_stays_on_the_workspace_form_with_a_flash(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/workspaces', ['name' => 'Stayed', 'submit_action' => 'save'])
            ->assertRedirect(route('workspaces.edit', Workspace::where('name', 'Stayed')->firstOrFail()))
            ->assertSessionHas('status', __('messages.saved'));
    }
}
