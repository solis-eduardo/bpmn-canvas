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

    /**
     * Validation rules for identifying a target User by either `user_id` or
     * `email`, exactly one required — the pair every endpoint using
     * resolveUserFrom() validates against.
     *
     * @return array<string, array<int, string>>
     */
    protected function userIdentifierRules(): array
    {
        return [
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email'],
        ];
    }
}
