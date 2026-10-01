<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Repositories\UserRepository;

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
}
