<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;

class DiagramWorkspacePolicy
{
    /**
     * Put a Diagram into $workspace: the Diagram Owner only, and only into a
     * Workspace they're a Member of (CONTEXT.md, "Diagram Owner").
     */
    public function assign(User $user, Diagram $diagram, Workspace $workspace): bool
    {
        return $diagram->isOwnedBy($user)
            && $workspace->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Take a Diagram out of its Workspace: the Diagram Owner, the
     * Workspace's own Owner, or an Administrator.
     */
    public function remove(User $user, Diagram $diagram): bool
    {
        return $diagram->isOwnedBy($user)
            || $user->is_admin
            || ($diagram->workspace?->isOwnedBy($user) ?? false);
    }
}
