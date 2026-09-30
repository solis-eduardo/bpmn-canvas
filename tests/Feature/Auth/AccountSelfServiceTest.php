<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_their_profile_with_read_only_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.com',
            'keycloak_id' => 'person-keycloak-id',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('person@example.com')
            ->assertSee('person-keycloak-id')
            ->assertDontSee('name="email"', false)
            ->assertSee('disabled', false)
            ->assertDontSee('name="keycloak_id"', false);
    }

    public function test_guest_cannot_view_their_profile(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_user_can_update_only_their_own_name_and_cannot_change_identity(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'person@example.com',
            'keycloak_id' => 'person-keycloak-id',
        ]);
        $other = User::factory()->create(['name' => 'Other User']);

        $this->actingAs($user)->put('/account', [
            'name' => 'Updated Name',
            'email' => 'attacker@example.com',
            'keycloak_id' => 'other-keycloak-id',
            'user_id' => $other->id,
        ])->assertRedirect('/account');

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('person@example.com', $user->fresh()->email);
        $this->assertSame('person-keycloak-id', $user->fresh()->keycloak_id);
        $this->assertSame('Other User', $other->fresh()->name);
    }

    public function test_local_user_can_change_password_with_current_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertRedirect('/account');

        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'incorrect-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_keycloak_user_cannot_change_password_in_the_application(): void
    {
        $user = User::factory()->create([
            'keycloak_id' => 'keycloak-person',
            'password' => 'random-password',
        ]);
        $passwordHash = $user->password;

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertDontSee('name="current_password"', false)
            ->assertDontSee('name="password"', false);

        $this->put('/account/password', [
            'current_password' => 'random-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertForbidden();

        $this->assertSame($passwordHash, $user->fresh()->password);
    }

    public function test_global_navigation_exposes_profile_and_sign_out_to_non_admin_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('diagrams.index'))
            ->assertOk()
            ->assertSee(route('account.edit'), false)
            ->assertSee(route('logout'), false);
    }
}
