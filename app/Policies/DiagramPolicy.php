<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;

class DiagramPolicy
{
    /**
     * Open the Editor to edit a Diagram: Author or approved Collaborator.
     */
    public function view(User $user, Diagram $diagram): bool
    {
        return $this->canEdit($user, $diagram);
    }

    /**
     * Save a Diagram's content: Author or approved Collaborator.
     */
    public function update(User $user, Diagram $diagram): bool
    {
        return $this->canEdit($user, $diagram);
    }

    /**
     * Manage a Diagram's Collaborators (invite/approve/remove): Author or
     * Administrator (spec.md, "Collaboration" section).
     */
    public function manageCollaborators(User $user, Diagram $diagram): bool
    {
        return $user->id === $diagram->author_id || $user->is_admin;
    }

    /**
     * Manage a Diagram's Shares (create/revoke): Author only, no exception
     * for Administrator — "Only the Author creates and revokes Shares"
     * (CONTEXT.md). Deliberately stricter than manageCollaborators; don't
     * unify the two.
     */
    public function manageShares(User $user, Diagram $diagram): bool
    {
        return $user->id === $diagram->author_id;
    }

    private function canEdit(User $user, Diagram $diagram): bool
    {
        if ($user->id === $diagram->author_id) {
            return true;
        }

        return DiagramCollaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $user->id)
            ->where('status', DiagramCollaborator::STATUS_APPROVED)
            ->exists();
    }
}
