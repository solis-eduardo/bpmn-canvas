<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Demo;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The browser demo's own actions (ADR-0018); every route is behind
 * EnsureDemoMode.
 */
class DemoController extends Controller
{
    /**
     * Signs in as one of the seeded demo accounts, no password needed.
     */
    public function signIn(Request $request, string $account): RedirectResponse
    {
        abort_unless(array_key_exists($account, Demo::ACCOUNTS), 404);

        $user = User::withDeactivated()->where('email', Demo::ACCOUNTS[$account]['email'])->firstOrFail();

        // A deactivated account never gets a session, demo sign-in included (ADR-0020).
        if ($user->isDeactivated()) {
            return redirect()->route('login')->with('error', __('messages.account_deactivated'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    /**
     * Wipes everything the visitor changed and seeds the demo data again.
     */
    public function reset(Request $request): RedirectResponse
    {
        Auth::logout();

        $disk = Storage::disk('diagrams');
        foreach ($disk->directories() as $directory) {
            $disk->deleteDirectory($directory);
        }

        Artisan::call('migrate:fresh', ['--seeder' => DemoSeeder::class, '--force' => true]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
