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
}
