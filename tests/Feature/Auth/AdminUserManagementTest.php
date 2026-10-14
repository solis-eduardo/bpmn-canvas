<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_the_last_administrator_cannot_remove_their_own_role(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->from('/admin/users')->put("/admin/users/{$admin->id}", [
            'is_admin' => false,
        ]);

        $response->assertSessionHasErrors(['is_admin' => __('messages.last_administrator_not_removable')]);
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_an_administrator_can_remove_their_own_role_when_another_remains(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['is_admin' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($admin->fresh()->is_admin);
    }

    public function test_a_keycloak_claim_administrator_counts_as_remaining_administrator(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => true, 'is_admin_via_keycloak_claim' => true]);

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['is_admin' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($admin->fresh()->is_admin);
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
            'login_method' => 'keycloak', 'keycloak_id' => 'keycloak-person',
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

    private function configureKeycloak(bool $configured = true): void
    {
        config([
            'services.keycloak.client_id' => $configured ? 'client-id' : null,
            'services.keycloak.client_secret' => $configured ? 'client-secret' : null,
            'services.keycloak.base_url' => $configured ? 'https://keycloak.example.test' : null,
        ]);
    }

    public function test_creating_a_user_requires_a_password(): void
    {
        $this->configureKeycloak();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Local Person',
            'email' => 'local@example.com',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'local@example.com']);
    }

    public function test_a_submitted_login_method_is_ignored_when_creating_a_user(): void
    {
        $this->configureKeycloak();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Sso Person',
            'email' => 'sso@example.com',
            'password' => 'initial-password',
            'login_method' => 'keycloak',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'sso@example.com', 'login_method' => 'local']);
    }

    public function test_create_form_has_no_login_method_field_even_when_keycloak_is_configured(): void
    {
        $this->configureKeycloak();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee(__('messages.create_user'))
            ->assertDontSee('name="login_method"', false)
            ->assertDontSee(__('messages.login_method'))
            ->assertDontSee('value="keycloak"', false);
    }

    public function test_created_user_defaults_to_the_local_method(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'initial-password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'login_method' => 'local']);
    }

    public function test_edit_form_hides_the_keycloak_method_when_keycloak_is_not_configured(): void
    {
        $this->configureKeycloak(false);
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('name="login_method"', false)
            ->assertDontSee('value="keycloak"', false);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'login_method' => 'keycloak',
        ])->assertSessionHasErrors('login_method');
    }

    public function test_edit_form_offers_the_keycloak_method_when_configured(): void
    {
        $this->configureKeycloak();
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertSee('value="keycloak"', false);
    }

    public function test_switching_to_keycloak_keeps_the_password_and_blocks_local_login(): void
    {
        $this->configureKeycloak();
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['password' => 'original-password']);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'login_method' => 'keycloak',
        ])->assertSessionHasNoErrors();

        $this->assertSame('keycloak', $target->fresh()->login_method);
        $this->assertTrue(Hash::check('original-password', $target->fresh()->password));
    }

    public function test_switching_back_to_local_restores_the_previous_password(): void
    {
        $this->configureKeycloak(false);
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create([
            'login_method' => 'keycloak', 'keycloak_id' => 'kc-1', 'password' => 'original-password',
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'login_method' => 'local',
        ])->assertSessionHasNoErrors();

        $this->assertSame('local', $target->fresh()->login_method);
        $this->assertTrue(Hash::check('original-password', $target->fresh()->password));

        auth()->logout();
        $this->post('/login', ['email' => $target->email, 'password' => 'original-password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($target);
    }

    public function test_switching_to_local_requires_a_password_when_the_account_never_had_one(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['login_method' => 'keycloak', 'password' => null]);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'login_method' => 'local',
        ])->assertSessionHasErrors('password');
        $this->assertSame('keycloak', $target->fresh()->login_method);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'login_method' => 'local',
            'password' => 'brand-new-password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('local', $target->fresh()->login_method);
        $this->assertTrue(Hash::check('brand-new-password', $target->fresh()->password));
    }

    public function test_editing_a_keycloak_user_without_keycloak_configured_keeps_the_method(): void
    {
        $this->configureKeycloak(false);
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'kc-1']);

        $this->actingAs($admin)->get(route('admin.users.edit', $target))
            ->assertDontSee('value="keycloak"', false);

        $this->actingAs($admin)->put(route('admin.users.update', $target), ['name' => 'Renamed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('keycloak', $target->fresh()->login_method);
    }

    public function test_non_admin_cannot_change_a_login_method(): void
    {
        $this->configureKeycloak();
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'login_method' => 'keycloak',
        ])->assertForbidden();

        $this->assertSame('local', $target->fresh()->login_method);
    }

    public function test_changing_a_login_method_revokes_the_users_sessions_and_remember_token(): void
    {
        $this->configureKeycloak();
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['login_method' => 'local', 'remember_token' => 'old-token']);
        $other = User::factory()->create();
        $this->insertSessionFor($target, 'target-1');
        $this->insertSessionFor($target, 'target-2');
        $this->insertSessionFor($other, 'other-1');

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'login_method' => 'keycloak',
        ]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-1']);
        $this->assertNotSame('old-token', $target->fresh()->remember_token);
    }

    public function test_updating_without_a_method_change_keeps_sessions_and_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['login_method' => 'local', 'remember_token' => 'old-token']);
        $this->insertSessionFor($target, 'target-1');

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => 'Renamed',
            'login_method' => 'local',
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'target-1']);
        $this->assertSame('old-token', $target->fresh()->remember_token);
    }

    public function test_admin_changing_their_own_method_is_logged_out(): void
    {
        $this->configureKeycloak();
        $admin = User::factory()->create(['is_admin' => true, 'login_method' => 'local']);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'login_method' => 'keycloak',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_editing_themselves_without_a_method_change_stays_logged_in(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'login_method' => 'local']);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Renamed Admin',
        ]);

        $this->assertAuthenticatedAs($admin);
    }

    private function insertSessionFor(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => time(),
        ]);
    }
}
