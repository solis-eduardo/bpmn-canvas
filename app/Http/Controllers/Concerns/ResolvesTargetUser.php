<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Repositories\UserRepository;
use Closure;

/**
 * @property-read UserRepository $users
 */
trait ResolvesTargetUser
{
    /**
     * Resolves a target User from validated request data containing either
     * a `user_id` or an `email` (see Controller::userIdentifierRules()).
     * Returns null if the email is provided but no matching User exists
     * (avoids email enumeration). The using class provides `$users`.
     */
    private function resolveUserFrom(array $data): ?User
    {
        if (isset($data['user_id'])) {
            return $this->users->findOrFail($data['user_id']);
        }

        return $this->users->findByEmail($data['email']);
    }

    /**
     * Runs $action for the target User, if there is one. An email that
     * matches nobody does nothing, silently: the caller answers it exactly
     * like a match (see targetedByEmail()), so the response never reveals
     * whether the email belongs to a User.
     *
     * @param  Closure(User): mixed  $action
     */
    private function actOnTargetUser(array $data, Closure $action): ?User
    {
        $target = $this->resolveUserFrom($data);

        if ($target) {
            $action($target);
        }

        return $target;
    }

    /**
     * True when the caller must answer generically: lookups by email can be
     * probed, lookups by user_id cannot (validation requires the ID to exist).
     */
    private function targetedByEmail(array $data): bool
    {
        return isset($data['email']);
    }
}
