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

    /**
     * Transfer the Workspace Owner role to another User: current Workspace
     * Owner or Administrator (CONTEXT.md, "Workspace Owner"; ADR-0006; issue
     * #29). Same rule as manageMembers, kept as its own ability the same way
     * Diagram Owner transfer is (DiagramPolicy::transferOwnership).
     */
    public function transferOwnership(User $user, Workspace $workspace): bool
    {
        return $this->canManage($user, $workspace);
    }

    /**
     * Delete a Workspace: Workspace Owner or Administrator (CONTEXT.md,
     * "Workspace Owner"). Refused separately, at the controller/Action
     * level, while the Workspace still holds Diagrams.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->canManage($user, $workspace);
    }

    private function canManage(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->is_admin;
    }
}
