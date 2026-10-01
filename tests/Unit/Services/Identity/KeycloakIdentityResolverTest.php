<?php

namespace Tests\Unit\Services\Identity;

use App\Models\User;
use App\Services\Identity\KeycloakIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class KeycloakIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    private KeycloakIdentityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(KeycloakIdentityResolver::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function identity(array $overrides = []): SocialiteUser
    {
        return SocialiteUser::fake($overrides + [
            'id' => 'kc-1',
            'nickname' => 'nick',
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'token' => 'x',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jwt(array $payload): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $encode(['alg' => 'none']).'.'.$encode($payload).'.sig';
    }

    public function test_returns_the_user_already_linked_to_the_keycloak_id(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-1']);

        $this->assertTrue($this->resolver->resolveUser($this->identity())->is($user));
    }

    public function test_links_a_local_account_with_the_same_email(): void
    {
        $local = User::factory()->create(['email' => 'ana@example.com']);

        $resolved = $this->resolver->resolveUser($this->identity());

        $this->assertTrue($resolved->is($local));
        $this->assertSame('kc-1', $resolved->keycloak_id);
        $this->assertSame(1, User::count());
    }

    public function test_builds_a_new_unsaved_user_otherwise(): void
    {
        $resolved = $this->resolver->resolveUser($this->identity());

        $this->assertFalse($resolved->exists);
        $this->assertSame('Ana', $resolved->name);
        $this->assertSame('kc-1', $resolved->keycloak_id);
        $this->assertNotEmpty($resolved->password);
    }

    public function test_falls_back_to_the_nickname_when_there_is_no_name(): void
    {
        $this->assertSame('nick', $this->resolver->resolveUser($this->identity(['name' => null]))->name);
    }

    public function test_an_account_linked_to_another_keycloak_identity_is_not_hijacked(): void
    {
        $other = User::factory()->create(['email' => 'ana@example.com', 'keycloak_id' => 'kc-other']);

        $resolved = $this->resolver->resolveUser($this->identity());

        $this->assertFalse($resolved->is($other));
        $this->assertSame('kc-other', $other->fresh()->keycloak_id);
    }

    public function test_decodes_the_token_payload(): void
    {
        $identity = $this->identity(['token' => $this->jwt(['groups' => ['a']])]);

        $this->assertSame(['groups' => ['a']], $this->resolver->decodeTokenPayload($identity));
    }

    public function test_an_unreadable_token_decodes_to_nothing(): void
    {
        $this->assertSame([], $this->resolver->decodeTokenPayload($this->identity(['token' => 'not-a-jwt'])));
        $this->assertSame([], $this->resolver->decodeTokenPayload($this->identity(['token' => 'a.%%%.c'])));
        $this->assertSame([], $this->resolver->decodeTokenPayload($this->identity(['token' => null])));
    }

    public function test_detects_the_admin_role_claim(): void
    {
        config(['bpmn.keycloak_admin_role' => 'bpmn-admin']);

        $this->assertTrue($this->resolver->hasAdminRoleClaim(['realm_access' => ['roles' => ['x', 'bpmn-admin']]]));
        $this->assertFalse($this->resolver->hasAdminRoleClaim(['realm_access' => ['roles' => ['x']]]));
        $this->assertFalse($this->resolver->hasAdminRoleClaim([]));
    }

    public function test_reads_groups_only_when_the_claim_is_a_list_of_strings(): void
    {
        $this->assertSame(['a', 'b'], $this->resolver->groupsFromPayload(['groups' => ['a', 'b']]));
        $this->assertSame([], $this->resolver->groupsFromPayload(['groups' => []]));
        $this->assertNull($this->resolver->groupsFromPayload([]));
        $this->assertNull($this->resolver->groupsFromPayload(['groups' => 'a']));
        $this->assertNull($this->resolver->groupsFromPayload(['groups' => ['a', 1]]));
    }

    public function test_only_keycloak_linked_users_have_an_externally_managed_password(): void
    {
        $this->assertTrue($this->resolver->passwordIsManagedExternally(User::factory()->make(['keycloak_id' => 'kc'])));
        $this->assertFalse($this->resolver->passwordIsManagedExternally(User::factory()->make(['keycloak_id' => null])));
    }
}
