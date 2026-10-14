<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Models\User;
use App\Models\Workspace;
use App\Support\KeycloakProvider;
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

    private function fakeSocialiteUser(
        string $keycloakId,
        string $name,
        string $email,
        array $realmRoles = [],
        ?array $groups = [],
        mixed $emailVerified = true,
    ): SocialiteUser {
        $jwt = $this->fakeJwt($realmRoles, $groups, $emailVerified);

        return SocialiteUser::fake([
            'id' => $keycloakId,
            'nickname' => $email,
            'name' => $name,
            'email' => $email,
            'token' => $jwt,
        ]);
    }

    private function fakeJwt(array $realmRoles = [], ?array $groups = [], mixed $emailVerified = true): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = [
            'realm_access' => ['roles' => $realmRoles],
        ];

        if ($emailVerified !== null) {
            $payload['email_verified'] = $emailVerified;
        }

        if ($groups !== null) {
            $payload['groups'] = $groups;
        }

        $payload = $encode($payload);

        return "{$header}.{$payload}.fake-signature";
    }

    private function mockSocialiteUser(SocialiteUser $user, ?string $idToken = null): void
    {
        $provider = Mockery::mock(KeycloakProvider::class);
        $provider->shouldReceive('user')->andReturn($user);
        $provider->shouldReceive('idToken')->andReturn($idToken);

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

    public function test_user_can_log_in_via_keycloak_and_reach_the_diagram_list(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-1', 'Ana Silva', 'ana@example.com'));

        $response = $this->get('/login/keycloak/callback');

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $this->get(route('diagrams.index'))->assertOk();
    }

    public function test_first_login_with_unknown_keycloak_id_provisions_a_new_account(): void
    {
        $this->assertDatabaseCount('users', 0);

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-2', 'Bruno Costa', 'bruno@example.com'));

        $this->get('/login/keycloak/callback');

        $this->assertDatabaseHas('users', [
            'login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-2',
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
            'login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-6',
            'is_admin' => true,
        ]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-6', $user->name, $user->email));

        $this->get('/login/keycloak/callback');

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_admin_role_claim_at_login_marks_the_claim_flag(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-claim-flag', 'Fabio Admin', 'fabio@example.com', ['bpmn-admin']
        ));

        $this->get('/login/keycloak/callback');

        $user = User::sole();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_admin_via_keycloak_claim);
    }

    public function test_absence_of_admin_role_claim_at_login_clears_a_claim_granted_administrator(): void
    {
        $user = User::factory()->create([
            'login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-revoke',
            'is_admin' => true, 'is_admin_via_keycloak_claim' => true,
        ]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-revoke', $user->name, $user->email));

        $this->get('/login/keycloak/callback');

        $user->refresh();
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->is_admin_via_keycloak_claim);
    }

    public function test_manual_revocation_is_allowed_once_a_login_without_the_claim_cleared_the_flag(): void
    {
        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn(
            $this->fakeSocialiteUser('keycloak-sub-revocable', 'Gabi Admin', 'gabi@example.com', ['bpmn-admin']),
            $this->fakeSocialiteUser('keycloak-sub-revocable', 'Gabi Admin', 'gabi@example.com'),
        );
        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $this->get('/login/keycloak/callback');
        $this->post('/logout');

        $claimAdmin = User::sole();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put("/admin/users/{$claimAdmin->id}", ['is_admin' => false])
            ->assertForbidden();

        $this->get('/login/keycloak/callback');
        $this->post('/logout');

        $this->actingAs($admin)->put("/admin/users/{$claimAdmin->id}", ['is_admin' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($claimAdmin->fresh()->is_admin);
    }

    public function test_claim_absent_at_login_keeps_a_manually_granted_administrator_as_manual(): void
    {
        $user = User::factory()->create([
            'login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-manual',
            'is_admin' => true, 'is_admin_via_keycloak_claim' => false,
        ]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-manual', $user->name, $user->email));

        $this->get('/login/keycloak/callback');

        $user->refresh();
        $this->assertTrue($user->is_admin);
        $this->assertFalse($user->is_admin_via_keycloak_claim);
    }

    public function test_unverified_login_with_the_claim_is_treated_as_claim_absent(): void
    {
        $user = User::factory()->create([
            'login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-unverified',
            'is_admin' => true, 'is_admin_via_keycloak_claim' => true,
        ]);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-unverified', $user->name, $user->email, ['bpmn-admin'], emailVerified: false
        ));

        $this->get('/login/keycloak/callback');

        $user->refresh();
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->is_admin_via_keycloak_claim);
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

    public function test_unverified_email_is_not_linked_to_an_existing_local_account(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com', 'keycloak_id' => null, 'is_admin' => true]);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'attacker-sub', 'Attacker', 'victim@example.com', ['bpmn-admin'], emailVerified: false,
        ));

        $this->get('/login/keycloak/callback')
            ->assertRedirect('/login')
            ->assertSessionHas('error', __('messages.keycloak_email_not_verified'));

        $this->assertGuest();
        $this->assertNull($victim->fresh()->keycloak_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_missing_or_non_boolean_email_verified_claim_is_treated_as_unverified(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com', 'keycloak_id' => null]);

        foreach ([null, 'true', 1] as $claim) {
            $this->mockSocialiteUser($this->fakeSocialiteUser(
                'attacker-sub', 'Attacker', 'victim@example.com', emailVerified: $claim,
            ));

            $this->get('/login/keycloak/callback')->assertRedirect('/login');
            $this->assertGuest();
        }

        $this->assertNull($victim->fresh()->keycloak_id);
    }

    public function test_unverified_email_does_not_provision_a_new_account(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'new-sub', 'New', 'new@example.com', emailVerified: false,
        ));

        $this->get('/login/keycloak/callback')->assertRedirect('/login');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verified_email_links_case_insensitively(): void
    {
        $local = User::factory()->create(['email' => 'Fatima@Example.com', 'keycloak_id' => null]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-ci', 'Fatima', 'fatima@example.com'));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('sub-ci', $local->fresh()->keycloak_id);
    }

    public function test_login_by_linked_sub_works_even_when_email_is_unverified_but_gets_no_admin_elevation(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'linked-sub']);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'linked-sub', $user->name, $user->email, ['bpmn-admin'], emailVerified: false,
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_email_of_an_account_linked_to_another_keycloak_identity_is_never_relinked(): void
    {
        $owner = User::factory()->create(['email' => 'taken@example.com', 'login_method' => 'keycloak', 'keycloak_id' => 'original-sub']);

        $this->mockSocialiteUser($this->fakeSocialiteUser('other-sub', 'Other', 'taken@example.com'));

        $this->get('/login/keycloak/callback')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame('original-sub', $owner->fresh()->keycloak_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_keycloak_login_adds_the_user_to_a_workspace_linked_to_one_of_their_groups(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-8']);
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'keycloak_group' => '/finance',
        ]);
        $this->addMember($workspace, $owner);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-8',
            $member->name,
            $member->email,
            groups: ['/finance'],
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertDatabaseHas('members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'origin' => 'group',
        ]);
    }

    public function test_keycloak_login_removes_group_membership_when_the_user_no_longer_has_the_linked_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-9']);
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'keycloak_group' => '/finance',
        ]);
        $this->addMember($workspace, $owner);
        $this->addGroupMember($workspace, $member);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-9',
            $member->name,
            $member->email,
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertDatabaseMissing('members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_keycloak_login_preserves_group_membership_when_the_groups_claim_is_missing(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-12']);
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'keycloak_group' => '/finance',
        ]);
        $this->addMember($workspace, $owner);
        $this->addGroupMember($workspace, $member);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-12',
            $member->name,
            $member->email,
            groups: null,
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertDatabaseHas('members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'origin' => 'group',
        ]);
    }

    public function test_keycloak_group_reconciliation_preserves_a_manual_membership_even_when_the_user_is_in_the_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-10']);
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'keycloak_group' => '/finance',
        ]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-10',
            $member->name,
            $member->email,
            groups: ['/finance'],
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertDatabaseHas('members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'origin' => 'manual',
        ]);

        $this->post('/logout');
        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-10',
            $member->name,
            $member->email,
        ));
        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertDatabaseHas('members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'origin' => 'manual',
        ]);
    }

    public function test_manually_adding_a_group_member_preserves_the_membership_after_they_leave_the_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-11']);
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'keycloak_group' => '/finance',
        ]);
        $this->addMember($workspace, $owner);
        $this->addGroupMember($workspace, $member);
        $this->addMember($workspace, $member);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'keycloak-sub-11',
            $member->name,
            $member->email,
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertDatabaseHas('members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'origin' => 'manual',
        ]);
    }

    public function test_verified_keycloak_login_converts_a_local_account_to_keycloak(): void
    {
        $local = User::factory()->create(['email' => 'lia@example.com', 'login_method' => 'local']);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-convert', $local->name, 'lia@example.com'));

        $this->get('/login/keycloak/callback');

        $this->assertAuthenticatedAs($local->fresh());
        $this->assertSame('keycloak', $local->fresh()->login_method);
        $this->assertSame('sub-convert', $local->fresh()->keycloak_id);
    }

    public function test_first_verified_login_links_a_keycloak_account_created_by_an_administrator(): void
    {
        $pending = User::factory()->create([
            'email' => 'pending@example.com', 'login_method' => 'keycloak', 'password' => null,
        ]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-pending', $pending->name, 'pending@example.com'));

        $this->get('/login/keycloak/callback');

        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($pending->fresh());
        $this->assertSame('sub-pending', $pending->fresh()->keycloak_id);
    }

    public function test_unverified_email_does_not_convert_a_local_account(): void
    {
        $local = User::factory()->create(['email' => 'lia@example.com', 'login_method' => 'local']);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-x', 'X', 'lia@example.com', emailVerified: false));

        $this->get('/login/keycloak/callback')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame('local', $local->fresh()->login_method);
    }

    public function test_provisioned_account_records_the_keycloak_login_method(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-new', 'New Person', 'newbie@example.com'));

        $this->get('/login/keycloak/callback');

        $this->assertDatabaseHas('users', ['email' => 'newbie@example.com', 'login_method' => 'keycloak']);
    }

    public function test_keycloak_login_keeps_the_id_token_in_the_session_for_logout(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-20', 'Lia Logout', 'lia@example.com'), 'id-token-20');

        $this->get('/login/keycloak/callback')
            ->assertSessionHas(KeycloakLoginController::ID_TOKEN_SESSION_KEY, 'id-token-20');
    }

    public function test_logging_out_a_keycloak_user_ends_the_keycloak_session(): void
    {
        $this->configureKeycloak();
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-21']);

        $response = $this->actingAs($user)
            ->withSession([KeycloakLoginController::ID_TOKEN_SESSION_KEY => 'id-token-21'])
            ->post('/logout');

        $response->assertRedirect(
            'https://sso.example.com/realms/solis/protocol/openid-connect/logout'
            .'?post_logout_redirect_uri='.urlencode('http://localhost/login')
            .'&client_id=bpmn-client&id_token_hint=id-token-21'
        );
        $response->assertSessionMissing(KeycloakLoginController::ID_TOKEN_SESSION_KEY);
        $this->assertGuest();
    }

    public function test_logging_out_a_keycloak_user_without_a_stored_id_token_returns_to_the_login_page(): void
    {
        $this->configureKeycloak();
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'keycloak-sub-22']);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_logging_out_a_local_user_does_not_end_any_keycloak_session(): void
    {
        $this->configureKeycloak();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([KeycloakLoginController::ID_TOKEN_SESSION_KEY => 'stale-id-token'])
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_local_login_discards_a_keycloak_id_token_left_in_the_session(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->withSession([KeycloakLoginController::ID_TOKEN_SESSION_KEY => 'stale-id-token'])
            ->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertSessionMissing(KeycloakLoginController::ID_TOKEN_SESSION_KEY);

        $this->assertAuthenticatedAs($user);
    }

    private function configureKeycloak(): void
    {
        config([
            'services.keycloak.client_id' => 'bpmn-client',
            'services.keycloak.client_secret' => 'bpmn-secret',
            'services.keycloak.base_url' => 'https://sso.example.com',
            'services.keycloak.realms' => 'solis',
        ]);
    }

    public function test_linked_login_refreshes_the_name_from_keycloak(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'sub-rename', 'name' => 'Old Name']);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-rename', 'New Name', $user->email));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_linked_login_keeps_the_name_when_keycloak_sends_no_name_claim(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'sub-noname', 'name' => 'Real Name']);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-noname', '', $user->email));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertSame('Real Name', $user->fresh()->name);
    }

    public function test_linked_login_refreshes_the_email_when_verified_and_not_taken(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'sub-mail', 'email' => 'old@example.com']);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-mail', $user->name, 'new@example.com'));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_linked_login_does_not_refresh_the_email_when_unverified(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'sub-unverified', 'email' => 'old@example.com']);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'sub-unverified', $user->name, 'new@example.com', emailVerified: false,
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_linked_login_does_not_take_an_email_held_by_another_account(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'sub-taken', 'email' => 'old@example.com']);
        $other = User::factory()->create(['email' => 'taken@example.com', 'keycloak_id' => null]);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-taken', $user->name, 'TAKEN@example.com'));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('old@example.com', $user->fresh()->email);
        $this->assertSame('taken@example.com', $other->fresh()->email);
        $this->assertNull($other->fresh()->keycloak_id);
    }

    public function test_linked_login_accepts_a_change_of_case_in_its_own_email(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'sub-case', 'email' => 'ana@example.com']);

        $this->mockSocialiteUser($this->fakeSocialiteUser('sub-case', $user->name, 'Ana@Example.com'));

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertSame('Ana@Example.com', $user->fresh()->email);
    }
}
