<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\User;

/**
 * Who can grant, request or revoke a Diagram's Collaborators (CONTEXT.md,
 * "Collaborator"; ADR-0006).
 */
class CollaboratorPolicy
{
    /**
     * Invite a User directly, or approve their request: Diagram Owner or
     * Administrator.
     */
    public function grant(User $user, Diagram $diagram): bool
    {
        return $diagram->isManagedBy($user);
    }

    /**
     * Add $target as a Collaborator: whoever can grant access may name
     * anyone; everyone else may only name themselves (a request). A null
     * $target is an email that matched nobody, which only those who can
     * grant may try.
     */
    public function invite(User $user, Diagram $diagram, ?User $target): bool
    {
        return $this->grant($user, $diagram) || ($target !== null && $user->is($target));
    }

    /**
     * Ask for access to a Diagram one can't edit yet. Those who can grant it
     * directly (Diagram Owner, Administrator) or already edit it have no
     * reason to ask.
     */
    public function requestAccess(User $user, Diagram $diagram): bool
    {
        return ! $this->grant($user, $diagram) && ! $diagram->isEditableBy($user);
    }

    /**
     * Decline a request or remove a Collaborator: same people who can grant.
     */
    public function revoke(User $user, Diagram $diagram): bool
    {
        return $this->grant($user, $diagram);
    }
}
