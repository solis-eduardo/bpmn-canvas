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
     * used alongside this everywhere it's called).
     */
    protected function resolveUserFrom(array $data): User
    {
        return isset($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : User::where('email', $data['email'])->firstOrFail();
    }
}
