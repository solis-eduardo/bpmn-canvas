<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

/**
 * Who can add, remove or leave a Workspace's Members (CONTEXT.md, "Member",
 * "Workspace Owner").
 */
class MemberPolicy
{
    /**
     * Add a Member: Workspace Owner or Administrator.
     */
    public function add(User $user, Workspace $workspace): bool
    {
        return $workspace->isManagedBy($user);
    }

    /**
     * Remove a Member: Workspace Owner or Administrator, but never the
     * Workspace Owner themselves — the Workspace would end up ownerless.
     */
    public function remove(User $user, Workspace $workspace, User $member): bool
    {
        return $this->add($user, $workspace) && ! $workspace->isOwnedBy($member);
    }

    /**
     * Leave the Workspace: any User except the Workspace Owner, who must
     * transfer ownership first.
     */
    public function leave(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isOwnedBy($user);
    }
}
