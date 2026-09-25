<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class KeycloakLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSocialiteUser(string $keycloakId, string $name, string $email, array $realmRoles = []): SocialiteUser
    {
        $jwt = $this->fakeJwt($realmRoles);

        return SocialiteUser::fake([
            'id' => $keycloakId,
            'nickname' => $email,
            'name' => $name,
            'email' => $email,
            'token' => $jwt,
        ]);
    }

    private function fakeJwt(array $realmRoles = []): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = $encode(['realm_access' => ['roles' => $realmRoles]]);

        return "{$header}.{$payload}.fake-signature";
    }

    private function mockSocialiteUser(SocialiteUser $user): void
    {
        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    public function test_callback_failure_redirects_to_login_with_error_instead_of_500(): void
    {
        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andThrow(
            new InvalidStateException
        );

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $response = $this->get('/login/keycloak/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_user_can_log_in_via_keycloak_and_reach_a_protected_route(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-1', 'Ana Silva', 'ana@example.com'));

        $response = $this->get('/login/keycloak/callback');

        $response->assertRedirect('/home');
        $this->assertAuthenticated();

        $this->get('/home')->assertOk();
    }

    public function test_first_login_with_unknown_keycloak_id_provisions_a_new_account(): void
    {
        $this->assertDatabaseCount('users', 0);

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-2', 'Bruno Costa', 'bruno@example.com'));

        $this->get('/login/keycloak/callback');

        $this->assertDatabaseHas('users', [
            'keycloak_id' => 'keycloak-sub-2',
            'name' => 'Bruno Costa',
            'email' => 'bruno@example.com',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_subsequent_login_with_the_same_keycloak_id_reuses_the_same_account(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-3', 'Carla Dias', 'carla@example.com'));
        $this->get('/login/keycloak/callback');
        $firstUserId = User::sole()->id;

        $this->post('/logout');

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-3', 'Carla Dias', 'carla@example.com'));
        $this->get('/login/keycloak/callback');

        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs(User::sole());
        $this->assertSame($firstUserId, User::sole()->id);
    }

    public function test_admin_role_claim_in_the_token_marks_the_user_as_admin(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-4',
            'Diego Admin',
            'diego@example.com',
            ['bpmn-admin']
        ));

        $this->get('/login/keycloak/callback');

        $this->assertTrue(User::sole()->is_admin);
    }

    public function test_absence_of_admin_role_claim_does_not_mark_the_user_as_admin(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-5',
            'Elisa No Admin',
            'elisa@example.com',
            ['some-other-role']
        ));

        $this->get('/login/keycloak/callback');

        $this->assertFalse(User::sole()->is_admin);
    }

    public function test_absence_of_admin_role_claim_never_revokes_a_manually_granted_admin_flag(): void
    {
        $user = User::factory()->create([
            'keycloak_id' => 'keycloak-sub-6',
            'is_admin' => true,
        ]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-6', $user->name, $user->email));

        $this->get('/login/keycloak/callback');

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_keycloak_login_links_to_an_existing_local_account_with_the_same_email_instead_of_duplicating(): void
    {
        $localUser = User::factory()->create([
            'email' => 'fatima@example.com',
            'keycloak_id' => null,
        ]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-7', $localUser->name, 'fatima@example.com'));

        $this->get('/login/keycloak/callback');

        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($localUser->fresh());
        $this->assertSame('keycloak-sub-7', $localUser->fresh()->keycloak_id);
    }
}
