<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\MemberService;
use App\Support\KeycloakResolution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as BaseRedirectResponse;
use Throwable;

class KeycloakLoginController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    public function redirect(): BaseRedirectResponse
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(Request $request, MemberService $members): RedirectResponse
    {
        try {
            $socialiteUser = Socialite::driver('keycloak')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect('/login')->with('error', __('messages.keycloak_login_failed'));
        }

        $tokenPayload = $this->tokenPayload($socialiteUser);
        $emailVerified = ($tokenPayload['email_verified'] ?? null) === true;

        $resolution = $this->resolveVerifiedUser($socialiteUser, $emailVerified);

        if ($resolution->isRejected()) {
            return redirect('/login')->with('error', __('messages.keycloak_email_not_verified'));
        }

        $user = $resolution->user;

        // Only a verified identity may be elevated: the claim is trusted, the
        // unverified email behind it is not.
        if ($emailVerified && $this->hasAdminRoleClaim($tokenPayload)) {
            $user->is_admin = true;
            $user->is_admin_via_keycloak_claim = true;
        }

        $user->save();
        $groups = $this->keycloakGroups($tokenPayload);
        $this->syncWorkspaceMemberships($user, $groups, $members);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * Rejected when the identity can't be tied to a local account safely:
     * linking or provisioning by email requires the IdP to vouch for that
     * email (`email_verified` strictly true), otherwise anyone able to
     * register the victim's email at the IdP could take over the victim's
     * local account.
     */
    private function resolveVerifiedUser(SocialiteUserContract $socialiteUser, bool $emailVerified): KeycloakResolution
    {
        $keycloakId = $socialiteUser->getId();

        // The stable `sub` always wins over email.
        $user = $this->users->findByKeycloakId($keycloakId);

        if ($user) {
            $user->login_method = User::LOGIN_METHOD_KEYCLOAK;
            $this->refreshProfile($user, $socialiteUser, $emailVerified);

            return KeycloakResolution::resolved($user);
        }

        $email = $socialiteUser->getEmail();

        if (! $emailVerified || ! is_string($email) || $email === '') {
            return KeycloakResolution::rejected();
        }

        // A pre-existing account with the same email (a `local` one, or a
        // `keycloak` one an Administrator created and that is still pending) is
        // linked to the Keycloak identity (same person) instead of creating a
        // duplicate account, which would also collide with the unique constraint
        // on `email`. Its login method becomes `keycloak` (ADR-0015).
        $user = $this->users->findUnlinkedByEmail($email);

        if ($user) {
            $user->keycloak_id = $keycloakId;
            $user->login_method = User::LOGIN_METHOD_KEYCLOAK;

            return KeycloakResolution::resolved($user);
        }

        // The email belongs to an account already linked to a different Keycloak
        // identity: never re-link it, and don't collide with the unique constraint.
        if ($this->users->findByEmail($email)) {
            return KeycloakResolution::rejected();
        }

        $user = new User([
            'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
            'email' => $email,
        ]);
        $user->keycloak_id = $keycloakId;
        $user->login_method = User::LOGIN_METHOD_KEYCLOAK;

        return KeycloakResolution::resolved($user);
    }

    /**
     * Keeps the name and email of an already linked account in step with the
     * IdP. The email is only taken from a verified claim and never when another
     * account already holds it, so a login can't reassign an address it doesn't
     * own. Changes are written to the log.
     */
    private function refreshProfile(User $user, SocialiteUserContract $socialiteUser, bool $emailVerified): void
    {
        $changed = [];

        // Only the `name` claim: falling back to the nickname (the IdP username)
        // would overwrite a real display name when the claim is absent.
        $name = $socialiteUser->getName();

        if (is_string($name) && $name !== '' && $name !== $user->name) {
            $user->name = $name;
            $changed[] = 'name';
        }

        $email = $socialiteUser->getEmail();

        if ($emailVerified && is_string($email) && $email !== '' && $email !== $user->email) {
            $holder = $this->users->findByEmail($email);

            if ($holder === null || $holder->is($user)) {
                $user->email = $email;
                $changed[] = 'email';
            } else {
                Log::warning('Keycloak login did not update the email: the address belongs to another account.', [
                    'user_id' => $user->id,
                    'holder_user_id' => $holder->id,
                ]);
            }
        }

        if ($changed !== []) {
            Log::info('Keycloak login refreshed the account profile.', [
                'user_id' => $user->id,
                'fields' => $changed,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasAdminRoleClaim(array $payload): bool
    {
        $roles = data_get($payload, 'realm_access.roles', []);

        return in_array(config('bpmn.keycloak_admin_role'), $roles, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>|null
     */
    private function keycloakGroups(array $payload): ?array
    {
        $groups = $payload['groups'] ?? null;

        if (! is_array($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if (! is_string($group)) {
                return null;
            }
        }

        return array_values($groups);
    }

    /**
     * @param  list<string>|null  $groups
     */
    private function syncWorkspaceMemberships(
        User $user,
        ?array $groups,
        MemberService $members,
    ): void {
        if ($groups === null) {
            Log::warning('Keycloak token is missing a valid groups claim; workspace membership sync was skipped.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $members->syncFromKeycloakGroups($user, $groups);
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(SocialiteUserContract $socialiteUser): array
    {
        $token = $socialiteUser->token ?? null;

        return is_string($token) ? $this->decodeJwtPayload($token) : [];
    }

    /**
     * Decodes a JWT's payload without verifying the signature.
     *
     * The access_token comes straight from Keycloak via a server-to-server
     * exchange (Socialite), so the source is already trusted at this point.
     *
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return [];
        }

        $payload = $this->base64UrlDecode($parts[1]);

        if ($payload === false) {
            return [];
        }

        return json_decode($payload, true) ?? [];
    }

    private function base64UrlDecode(string $data): string|false
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');

        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}
