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
        return $diagram->isOwnedBy($user) && $workspace->hasMember($user);
    }

    /**
     * Take a Diagram out of its Workspace: the Diagram Owner, the
     * Workspace's own Owner, or an Administrator.
     */
    public function remove(User $user, Diagram $diagram): bool
    {
        return $diagram->isManagedBy($user)
            || ($diagram->workspace?->isOwnedBy($user) ?? false);
    }
}
