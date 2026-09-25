<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'initial-password',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'is_admin' => false,
        ]);
    }

    public function test_non_admin_cannot_create_a_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'initial-password',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_guest_cannot_create_a_user(): void
    {
        $response = $this->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'initial-password',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_grant_admin_role_to_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'is_admin' => true,
        ]);

        $response->assertRedirect();
        $this->assertTrue($target->fresh()->is_admin);
    }

    public function test_admin_can_revoke_admin_role_from_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'is_admin' => false,
        ]);

        $response->assertRedirect();
        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_non_admin_cannot_grant_admin_role(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->put("/admin/users/{$target->id}", [
            'is_admin' => true,
        ]);

        $response->assertForbidden();
        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_admin_can_access_user_management_screen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertViewIs('admin.users.index');
    }

    public function test_non_admin_cannot_access_user_management_screen(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_user_management_screen(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    }

    public function test_user_management_screen_displays_all_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user1 = User::factory()->create(['is_admin' => false]);
        $user2 = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertViewHas('users');
        $users = $response->viewData('users');
        $this->assertCount(3, $users);
        $this->assertTrue($users->contains($admin));
        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
    }

    public function test_user_management_screen_displays_login_method(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $local_user = User::factory()->create(['keycloak_id' => null]);
        $keycloak_user = User::factory()->create(['keycloak_id' => 'keycloak-123']);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $this->assertStringContainsString('Local', $response->getContent());
        $this->assertStringContainsString('Keycloak SSO', $response->getContent());
    }

    public function test_user_management_screen_shows_admin_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin_user = User::factory()->create(['is_admin' => true]);
        $regular_user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $this->assertCount(1, $response->viewData('users')->where('is_admin', false));
        $this->assertCount(2, $response->viewData('users')->where('is_admin', true));
    }

    public function test_keycloak_admin_role_cannot_be_revoked(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $local_admin = User::factory()->create(['is_admin' => true, 'keycloak_id' => null]);
        $keycloak_admin = User::factory()->create([
            'is_admin' => true,
            'keycloak_id' => 'keycloak-123',
        ]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $content = $response->getContent();

        // Verify that local admin has revoke button
        $this->assertStringContainsString('revoke_admin', $content);
        // Verify that Keycloak role indicator is shown
        $this->assertStringContainsString('from Keycloak', $content);
    }
}
