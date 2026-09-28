<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolves a target User from validated request data containing either
     * a `user_id` or an `email` (see the `required_without` validation rule
     * used alongside this everywhere it's called). Returns null if the email
     * is provided but no matching User exists (avoids email enumeration).
     */
    protected function resolveUserFrom(array $data): ?User
    {
        if (isset($data['user_id'])) {
            return User::findOrFail($data['user_id']);
        }

        return User::where('email', $data['email'])->first();
    }
}
