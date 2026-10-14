<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Demo;
use App\Support\KeycloakConfig;
use App\Support\KeycloakProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            'keycloakConfigured' => ! Demo::enabled() && KeycloakConfig::isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // A `keycloak` User never signs in locally (ADR-0015); the same
        // generic error as a wrong password avoids revealing the method.
        $credentials['login_method'] = User::LOGIN_METHOD_LOCAL;

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('messages.invalid_credentials'),
            ]);
        }

        // regenerate() keeps session data, so a Keycloak ID token left over from an
        // earlier SSO login must not survive into this local session.
        $request->session()->regenerate();
        $request->session()->forget(KeycloakLoginController::ID_TOKEN_SESSION_KEY);

        return redirect()->intended(route('home'));
    }

    /**
     * A Keycloak User's SSO session lives at the IdP, so signing out also ends it
     * there (RP-initiated logout); otherwise the next Keycloak sign-in would
     * succeed without credentials. Local Users only lose the local session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $idToken = $request->session()->pull(KeycloakLoginController::ID_TOKEN_SESSION_KEY);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user instanceof User && $user->usesKeycloak() && is_string($idToken) && KeycloakConfig::isConfigured()) {
            return redirect()->away($this->keycloakLogoutUrl($idToken));
        }

        return redirect('/login');
    }

    private function keycloakLogoutUrl(string $idToken): string
    {
        /** @var KeycloakProvider $provider */
        $provider = Socialite::driver('keycloak');

        return $provider->getLogoutUrl(url('/login'), config('services.keycloak.client_id'), $idToken);
    }
}
