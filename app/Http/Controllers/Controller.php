<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Validation rules for identifying a target User by either `user_id` or
     * `email`, exactly one required — the pair every endpoint using
     * ResolvesTargetUser::resolveUserFrom() validates against.
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
