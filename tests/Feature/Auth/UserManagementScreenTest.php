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
        $response->assertSee('Regular Person');
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
