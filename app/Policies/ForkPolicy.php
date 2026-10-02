<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;

class ForkPolicy
{
    /**
     * Fork a Diagram: anyone who may open it, and — when the Fork is to be
     * placed in a Workspace — only into one the User is a Member of.
     */
    public function create(User $user, Diagram $diagram, ?Workspace $workspace = null): bool
    {
        if (! $diagram->isOpenableBy($user)) {
            return false;
        }

        return $workspace === null || $workspace->hasMember($user);
    }
}
