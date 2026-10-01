<?php

namespace App\Actions\Diagrams;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;

/**
 * Direct invite by the Author/Administrator (immediate access, status
 * "approved") or self-request by the User themselves (status "requested"):
 * if the requester is the Author or an Administrator, the row is written as
 * "approved", regardless of who the target is; otherwise, it's only allowed
 * when the target is the requester themselves, and a self-request never
 * downgrades an already-approved Collaborator back to "requested".
 */
class GrantOrRequestCollaborator
{
    public function __invoke(Diagram $diagram, User $requester, User $target): Collaborator
    {
        $canGrantDirectly = $requester->can('manageCollaborators', $diagram);

        if (! $canGrantDirectly && $requester->id !== $target->id) {
            abort(403);
        }

        $existing = Collaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $target->id)
            ->first();

        $status = $canGrantDirectly
            ? Collaborator::STATUS_APPROVED
            : ($existing->status ?? Collaborator::STATUS_REQUESTED);

        return Collaborator::updateOrCreate(
            ['diagram_id' => $diagram->id, 'user_id' => $target->id],
            ['status' => $status]
        );
    }
}
