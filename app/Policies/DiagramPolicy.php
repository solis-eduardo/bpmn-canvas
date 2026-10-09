<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\User;

class DiagramPolicy
{
    /**
     * Open the Editor to edit a Diagram, fetch its content, fork it, or
     * request the Lock: Diagram Owner, approved Collaborator, or Member of
     * the Diagram's Workspace (issue #27) — plus an Administrator, who
     * "sees and opens" every Diagram (issue #28) even without edit rights.
     */
    public function view(User $user, Diagram $diagram): bool
    {
        return $diagram->isEditableBy($user) || $user->is_admin;
    }

    /**
     * Save a Diagram's content: Diagram Owner, approved Collaborator, or
     * Member of the Diagram's Workspace (issue #27). Deliberately no
     * Administrator bypass — "sees and opens" doesn't mean "can edit".
     */
    public function update(User $user, Diagram $diagram): bool
    {
        return $diagram->isEditableBy($user);
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
     * Owner, Diagram Manager or Administrator (CONTEXT.md, "Collaborator";
     * ADR-0006, ADR-0017).
     */
    public function manageCollaborators(User $user, Diagram $diagram): bool
    {
        return $diagram->isManagedBy($user) || $diagram->hasManagerRole($user);
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
     * Apply or remove a Diagram's Tags: same eligibility as editing it —
     * Diagram Owner, approved Collaborator, or Member of its Workspace
     * (CONTEXT.md, "Tag"; issue #40). Unlike manageCollaborators/
     * manageShares, this is deliberately not Owner-only.
     */
    public function manageTags(User $user, Diagram $diagram): bool
    {
        return $diagram->isEditableBy($user);
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

    /**
     * Put a Diagram into a Workspace: Diagram Owner only, and only into a
     * Workspace they're a Member of (CONTEXT.md, "Diagram Owner"; issue
     * #27). The Member-of-target-Workspace check happens in the controller,
     * since it needs the target Workspace, not just the Diagram.
     */
    public function putInWorkspace(User $user, Diagram $diagram): bool
    {
        return $diagram->isOwnedBy($user);
    }

    /**
     * Take a Diagram out of its Workspace: Diagram Owner or the Workspace's
     * own Owner (CONTEXT.md, "Workspace Owner"; issue #27), or an
     * Administrator, who "can manage any Workspace".
     */
    public function removeFromWorkspace(User $user, Diagram $diagram): bool
    {
        if ($diagram->isOwnedBy($user) || $user->is_admin) {
            return true;
        }

        return $diagram->workspace !== null && $diagram->workspace->isOwnedBy($user);
    }
}
