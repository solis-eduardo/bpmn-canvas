<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Workspace;
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

    public function test_user_can_log_in_via_keycloak_and_reach_the_diagram_list(): void
    {
        $this->mockSocialiteUser($this->fakeSocialiteUser('keycloak-sub-1', 'Ana Silva', 'ana@example.com'));

        $response = $this->get('/login/keycloak/callback');

        $response->assertRedirect(route('diagrams.index'));
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

        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('sub-ci', $local->fresh()->keycloak_id);
    }

    public function test_login_by_linked_sub_works_even_when_email_is_unverified_but_gets_no_admin_elevation(): void
    {
        $user = User::factory()->create(['login_method' => 'keycloak', 'keycloak_id' => 'linked-sub']);

        $this->mockSocialiteUser($this->fakeSocialiteUser(
            'linked-sub', $user->name, $user->email, ['bpmn-admin'], emailVerified: false,
        ));

        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

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

        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

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

        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

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

        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

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

        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

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
        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

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

        $this->get('/login/keycloak/callback')->assertRedirect(route('diagrams.index'));

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
}
