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

            return redirect('/login')->with('error', 'Não foi possível entrar com o Keycloak. Tente novamente.');
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

        // Uma conta local pré-existente com o mesmo e-mail é vinculada à identidade
        // Keycloak (mesma pessoa) em vez de gerar uma conta duplicada, o que também
        // colidiria com a constraint unique de `email`.
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
     * Decodifica o payload de um JWT sem verificar a assinatura.
     *
     * O access_token vem direto do Keycloak via troca server-to-server
     * (Socialite), então a origem já é confiável neste ponto.
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
