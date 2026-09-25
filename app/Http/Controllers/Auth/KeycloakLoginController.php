<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class KeycloakLoginController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $socialiteUser = Socialite::driver('keycloak')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect('/login')->with('error', __('messages.keycloak_login_failed'));
        }

        $user = $this->resolveUser($socialiteUser);

        if ($this->hasAdminRoleClaim($socialiteUser)) {
            $user->is_admin = true;
        }

        $user->save();

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended('/home');
    }

    private function resolveUser(SocialiteUserContract $socialiteUser): User
    {
        $keycloakId = $socialiteUser->getId();

        $user = User::where('keycloak_id', $keycloakId)->first();

        if ($user) {
            return $user;
        }

        // A pre-existing local account with the same email is linked to the Keycloak
        // identity (same person) instead of creating a duplicate account, which would
        // also collide with the unique constraint on `email`.
        $user = User::whereNull('keycloak_id')
            ->where('email', $socialiteUser->getEmail())
            ->first();

        if ($user) {
            $user->keycloak_id = $keycloakId;

            return $user;
        }

        $user = new User([
            'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
            'email' => $socialiteUser->getEmail(),
            'password' => Hash::make(Str::random(40)),
        ]);
        $user->keycloak_id = $keycloakId;

        return $user;
    }

    private function hasAdminRoleClaim(SocialiteUserContract $socialiteUser): bool
    {
        $token = $socialiteUser->token ?? null;

        if (! is_string($token)) {
            return false;
        }

        $payload = $this->decodeJwtPayload($token);

        $roles = data_get($payload, 'realm_access.roles', []);

        return in_array(config('bpmn.keycloak_admin_role'), $roles, true);
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
