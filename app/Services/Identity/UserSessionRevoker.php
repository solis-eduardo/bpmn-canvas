<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends a User's existing authentication when their login method changes
 * (ADR-0015): a session or remember-me cookie created under the old method
 * must not outlive it.
 */
class UserSessionRevoker
{
    /**
     * Rotates the remember-me token and, with the `database` session driver,
     * deletes the User's session rows. Other drivers cannot enumerate sessions
     * by User, so only the remember-me token is invalidated there.
     */
    public function revoke(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
