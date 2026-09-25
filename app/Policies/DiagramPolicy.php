<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;

class DiagramPolicy
{
    /**
     * Open the Editor to edit a Diagram: Diagram Owner or approved
     * Collaborator.
     */
    public function view(User $user, Diagram $diagram): bool
    {
        return $this->canEdit($user, $diagram);
    }

    /**
     * Save a Diagram's content: Diagram Owner or approved Collaborator.
     */
    public function update(User $user, Diagram $diagram): bool
    {
        return $this->canEdit($user, $diagram);
    }

    /**
     * Delete a Diagram: Diagram Owner only (ADR-0006 — this power moved off
     * the Author, which stays credit only).
     */
    public function delete(User $user, Diagram $diagram): bool
    {
        return $diagram->isOwnedBy($user);
    }

    /**
     * Manage a Diagram's Collaborators (invite/approve/remove): Diagram
     * Owner or Administrator (CONTEXT.md, "Collaborator"; ADR-0006).
     */
    public function manageCollaborators(User $user, Diagram $diagram): bool
    {
        return $diagram->isOwnedBy($user) || $user->is_admin;
    }

    /**
     * Manage a Diagram's Shares (create/revoke): Diagram Owner only, no
     * exception for Administrator — "Only the Diagram Owner creates and
     * revokes Shares" (CONTEXT.md, "Share"; ADR-0006). Deliberately
     * stricter than manageCollaborators; don't unify the two.
     */
    public function manageShares(User $user, Diagram $diagram): bool
    {
        return $diagram->isOwnedBy($user);
    }

    /**
     * Transfer the Diagram Owner role to another User: current Diagram
     * Owner or Administrator (CONTEXT.md, "Diagram Owner"; ADR-0006; issue
     * #14). Same rule as manageCollaborators, kept as its own ability since
     * the two are conceptually distinct and may diverge later.
     */
    public function transferOwnership(User $user, Diagram $diagram): bool
    {
        return $diagram->isOwnedBy($user) || $user->is_admin;
    }

    private function canEdit(User $user, Diagram $diagram): bool
    {
        if ($diagram->isOwnedBy($user)) {
            return true;
        }

        return DiagramCollaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $user->id)
            ->where('status', DiagramCollaborator::STATUS_APPROVED)
            ->exists();
    }
}
