<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a deactivated User whose session survived deactivation (ADR-0020).
 * The session revocation covers the database driver; this covers the others
 * (file, cookie), which cannot enumerate sessions by User.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isDeactivated()) {
            Auth::guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('messages.account_deactivated'));
        }

        return $next($request);
    }
}
