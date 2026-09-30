<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_create_validation_preserves_safe_values_but_not_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($admin)->from(route('admin.users.create'))->post('/admin/users', [
            'name' => 'Safe Name',
            'email' => 'existing@example.com',
            'password' => 'never-flash-this',
        ]);

        $response->assertRedirect(route('admin.users.create'));
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('_old_input.name', 'Safe Name');
        $response->assertSessionHas('_old_input.email', 'existing@example.com');
        $response->assertSessionMissing('_old_input.password');
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

    public function test_admin_can_update_a_local_user_and_replace_the_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['password' => 'original-password']);

        $response = $this->actingAs($admin)->from(route('admin.users.edit', $target))
            ->put(route('admin.users.update', $target), [
                'name' => 'Updated Name',
                'password' => 'replacement-password',
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $this->assertSame('Updated Name', $target->fresh()->name);
        $this->assertTrue(Hash::check('replacement-password', $target->fresh()->password));
    }

    public function test_blank_admin_password_preserves_the_local_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['password' => 'original-password']);

        $this->actingAs($admin)->from(route('admin.users.edit', $target))
            ->put(route('admin.users.update', $target), [
                'name' => 'Updated Name',
                'password' => '',
            ])->assertRedirect(route('admin.users.edit', $target));

        $this->assertTrue(Hash::check('original-password', $target->fresh()->password));
    }

    public function test_admin_cannot_set_a_local_password_for_a_keycloak_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create([
            'keycloak_id' => 'keycloak-person',
            'password' => 'existing-random-password',
        ]);
        $passwordHash = $target->password;

        $response = $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => 'Updated Name',
            'password' => 'replacement-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame($passwordHash, $target->fresh()->password);
        $this->assertSame($target->name, $target->fresh()->name);
    }

    public function test_edit_validation_preserves_name_but_not_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.update', $target), [
                'name' => '',
                'password' => 'short',
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $response->assertSessionHasErrors(['name', 'password']);
        $response->assertSessionMissing('_old_input.password');
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
