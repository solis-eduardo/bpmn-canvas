<?php

namespace App\Actions\Diagrams;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\CollaboratorRepository;

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
    public function __construct(private readonly CollaboratorRepository $collaborators) {}

    public function __invoke(Diagram $diagram, User $requester, User $target): Collaborator
    {
        $canGrantDirectly = $requester->can('manageCollaborators', $diagram);

        if (! $canGrantDirectly && $requester->id !== $target->id) {
            abort(403);
        }

        $existing = $this->collaborators->findFor($diagram, $target);

        $status = $canGrantDirectly
            ? Collaborator::STATUS_APPROVED
            : ($existing->status ?? Collaborator::STATUS_REQUESTED);

        return $this->collaborators->upsertStatus($diagram, $target, $status);
    }
}
