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
        if (! ($diagram->isEditableBy($user) || $user->is_admin)) {
            return false;
        }

        return $workspace === null || $workspace->members()->where('user_id', $user->id)->exists();
    }
}
