<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Repositories\UserRepository;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Turns what Keycloak told us after login into a local User, an
 * Administrator flag and a list of groups (ADR-0001, ADR-0005).
 */
class KeycloakIdentityResolver
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * The local User for this Keycloak identity: the one already linked, a
     * pre-existing local account with the same email (linked now, never
     * duplicated), or a new one. Not saved — the caller decides when.
     */
    public function resolveUser(SocialiteUser $socialiteUser): User
    {
        $keycloakId = $socialiteUser->getId();

        $user = $this->users->findByKeycloakId($keycloakId);

        if ($user) {
            $user->login_method = User::LOGIN_METHOD_KEYCLOAK;

            return $user;
        }

        $user = $this->users->findUnlinkedByEmail($socialiteUser->getEmail())
            ?? new User([
                'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
                'email' => $socialiteUser->getEmail(),
            ]);
        $user->keycloak_id = $keycloakId;
        $user->login_method = User::LOGIN_METHOD_KEYCLOAK;

        return $user;
    }

    /**
     * The access token's claims, or [] if it isn't a readable JWT. The
     * signature is not verified: the token comes straight from Keycloak via
     * Socialite's server-to-server exchange, so the source is already trusted.
     *
     * @return array<string, mixed>
     */
    public function decodeTokenPayload(SocialiteUser $socialiteUser): array
    {
        $token = $socialiteUser->token ?? null;

        if (! is_string($token)) {
            return [];
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return [];
        }

        $payload = $this->base64UrlDecode($parts[1]);

        return $payload === false ? [] : (json_decode($payload, true) ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasAdminRoleClaim(array $payload): bool
    {
        $roles = data_get($payload, 'realm_access.roles', []);

        return in_array(config('bpmn.keycloak_admin_role'), $roles, true);
    }

    /**
     * The payload's group names, or null when the claim is missing or
     * malformed (so a sync is skipped instead of wiping memberships).
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>|null
     */
    public function groupsFromPayload(array $payload): ?array
    {
        $groups = $payload['groups'] ?? null;

        if (! is_array($groups) || array_filter($groups, 'is_string') !== $groups) {
            return null;
        }

        return array_values($groups);
    }

    /**
     * True for a User linked to Keycloak: their password lives there, not here.
     */
    public function passwordIsManagedExternally(User $user): bool
    {
        return $user->usesKeycloak();
    }

    private function base64UrlDecode(string $data): string|false
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');

        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}
