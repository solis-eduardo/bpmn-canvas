<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Demo;
use App\Support\KeycloakConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

        // A deactivated User can't sign in either (ADR-0020); the same generic
        // error hides that from anyone guessing credentials.
        $credentials['deactivated_at'] = null;

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('messages.invalid_credentials'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
