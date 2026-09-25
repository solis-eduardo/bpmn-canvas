<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Create a Workspace: any authenticated User (CONTEXT.md, "Workspace" —
     * "Any User can create a Workspace").
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * See a Workspace's detail (its Members, and — for the Workspace Owner
     * or an Administrator — its management controls): Member or
     * Administrator.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user) || $user->is_admin;
    }

    /**
     * Rename a Workspace: Workspace Owner or Administrator (CONTEXT.md,
     * "Workspace Owner" — "other Members cannot").
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->is_admin;
    }

    /**
     * Manage a Workspace's Members (add/remove): Workspace Owner or
     * Administrator (CONTEXT.md, "Workspace Owner"; "Administrator" — "can
     * manage any Workspace").
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->is_admin;
    }
}
