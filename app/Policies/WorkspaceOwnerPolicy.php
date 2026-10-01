<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceOwnerPolicy
{
    /**
     * Transfer the Workspace Owner role: the current Workspace Owner or an
     * Administrator (CONTEXT.md, "Workspace Owner"; ADR-0006).
     */
    public function transfer(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->is_admin;
    }
}
