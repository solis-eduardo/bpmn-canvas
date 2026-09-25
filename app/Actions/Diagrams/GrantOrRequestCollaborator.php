<?php

namespace App\Actions\Diagrams;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
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
    public function __invoke(Diagram $diagram, User $requester, User $target): DiagramCollaborator
    {
        $canGrantDirectly = $requester->can('manageCollaborators', $diagram);

        if (! $canGrantDirectly && $requester->id !== $target->id) {
            abort(403);
        }

        $existing = DiagramCollaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $target->id)
            ->first();

        $status = $canGrantDirectly
            ? DiagramCollaborator::STATUS_APPROVED
            : ($existing->status ?? DiagramCollaborator::STATUS_REQUESTED);

        return DiagramCollaborator::updateOrCreate(
            ['diagram_id' => $diagram->id, 'user_id' => $target->id],
            ['status' => $status]
        );
    }
}
