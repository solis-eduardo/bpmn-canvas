<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Rename a Workspace: Workspace Owner or Administrator (CONTEXT.md,
     * "Workspace Owner").
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $this->canManage($user, $workspace);
    }

    /**
     * Add or remove a Workspace's Members: Workspace Owner or Administrator
     * (CONTEXT.md, "Member"); other Members can't.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->canManage($user, $workspace);
    }

    private function canManage(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->is_admin;
    }
}
