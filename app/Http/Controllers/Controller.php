<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * The options of a User picker (components/user-select), skipping `$except`.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array{id: int, name: string, email: string}>
     */
    protected function userOptions(Collection $users, ?User $except = null): array
    {
        return $users
            ->reject(fn (User $candidate) => $except !== null && $candidate->is($except))
            ->map(fn (User $candidate) => ['id' => $candidate->id, 'name' => $candidate->name, 'email' => $candidate->email])
            ->values()
            ->all();
    }

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
