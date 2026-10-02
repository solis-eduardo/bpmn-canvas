<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_user_management_screen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => false, 'name' => 'Regular Person']);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('<table', false);
        $response->assertSee('<thead ', false);
        $response->assertSee('<tbody ', false);
        $response->assertSee('scope="col"', false);
        $response->assertSee(__('messages.name'));
        $response->assertSee(__('messages.email'));
        $response->assertSee(__('messages.login_method'));
        $response->assertSee(__('messages.administrator_status'));
        $response->assertSee(__('messages.user_actions'));
        $response->assertSee('Regular Person');
        $response->assertSee($other->email);
        $response->assertSee(__('messages.login_method_local'));
        $response->assertSee(__('messages.grant_administrator'));
        $response->assertSee(route('admin.users.edit', $other), false);
        $response->assertSee('overflow-x-auto', false);
        $response->assertSee(route('admin.users.create'), false);
    }

    public function test_empty_user_management_listing_has_a_semantic_empty_table_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $admin->delete();

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('<tbody ', false)
            ->assertSee('colspan="5"', false)
            ->assertSee(__('messages.no_users_yet'));
    }

    public function test_admin_can_view_the_dedicated_user_creation_screen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_non_admin_cannot_view_the_user_creation_screen(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    }

    public function test_guest_cannot_view_the_user_creation_screen(): void
    {
        $this->get('/admin/users/create')->assertRedirect('/login');
    }

    public function test_admin_can_view_a_dedicated_user_edit_screen_with_read_only_identity(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create([
            'name' => 'Regular Person',
            'email' => 'person@example.com',
            'keycloak_id' => 'person-keycloak-id',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('Regular Person')
            ->assertSee('person@example.com')
            ->assertSee('person-keycloak-id')
            ->assertSee('readonly', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="keycloak_id"', false);
    }

    public function test_local_user_edit_screen_offers_an_optional_password_field(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['email' => 'local@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('local@example.com')
            ->assertSee('name="password"', false)
            ->assertSee('readonly', false);
    }

    public function test_non_admin_cannot_view_a_user_edit_screen(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.edit', $target))->assertForbidden();
    }

    public function test_guest_cannot_view_a_user_edit_screen(): void
    {
        $target = User::factory()->create();

        $this->get(route('admin.users.edit', $target))->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_the_user_management_screen(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_the_user_management_screen(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    }

    public function test_admin_cannot_revoke_an_administrator_role_granted_via_keycloak_claim(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create([
            'is_admin' => true,
            'is_admin_via_keycloak_claim' => true,
            'keycloak_id' => 'keycloak-sub-1',
        ]);

        $response = $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'is_admin' => false,
        ]);

        $response->assertForbidden();
        $this->assertTrue($target->fresh()->is_admin);
    }

    public function test_administrator_granted_via_keycloak_claim_is_not_manually_revocable(): void
    {
        $claimAdmin = User::factory()->create([
            'is_admin' => true,
            'is_admin_via_keycloak_claim' => true,
        ]);
        $manualAdmin = User::factory()->create([
            'is_admin' => true,
            'is_admin_via_keycloak_claim' => false,
        ]);

        $this->assertFalse($claimAdmin->adminRoleIsManuallyRevocable());
        $this->assertTrue($manualAdmin->adminRoleIsManuallyRevocable());
    }
}
