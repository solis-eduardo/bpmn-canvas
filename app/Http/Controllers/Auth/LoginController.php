<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            'keycloakConfigured' => $this->keycloakConfigured(),
        ]);
    }

    /**
     * Keycloak is considered configured once the OAuth client credentials and
     * the realm base URL it redirects to are all present (see
     * config/services.php, sourced from KEYCLOAK_* env vars).
     */
    private function keycloakConfigured(): bool
    {
        return filled(config('services.keycloak.client_id'))
            && filled(config('services.keycloak.client_secret'))
            && filled(config('services.keycloak.base_url'));
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('messages.invalid_credentials'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('diagrams.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
