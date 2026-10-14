<?php

namespace App\Services;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Notifications\DiagramAccessRequested;
use App\Notifications\DiagramAccessRequestResolved;
use App\Notifications\DiagramCollaboratorInvited;
use App\Notifications\DiagramCollaboratorRemoved;
use App\Notifications\DiagramCollaboratorRoleChanged;
use App\Repositories\CollaboratorRepository;

/**
 * A Diagram's Collaborators and the notification each change deserves.
 * Whether the caller may do any of this is CollaboratorPolicy's call.
 */
class CollaboratorService
{
    public function __construct(
        private readonly CollaboratorRepository $collaborators,
        private readonly LockService $locks,
    ) {}

    /**
     * Adds $target either way the caller is allowed to: directly when
     * $direct (CollaboratorPolicy::grant), otherwise as a pending request.
     */
    public function invite(Diagram $diagram, User $target, bool $direct, ?string $role = null): Collaborator
    {
        return $direct ? $this->grant($diagram, $target, $role) : $this->requestAccess($diagram, $target);
    }

    /**
     * Direct invite: the target gets access immediately, and is told — unless
     * they already had it. A null $role keeps the role of someone already
     * there (and makes a new Collaborator plain); a role change on its own
     * is told as such.
     */
    public function grant(Diagram $diagram, User $target, ?string $role = null): Collaborator
    {
        $collaborator = $this->collaborators->upsertApproved($diagram, $target, $role);

        if ($collaborator->wasRecentlyCreated || $collaborator->wasChanged('status')) {
            $target->notify(new DiagramCollaboratorInvited($diagram, $collaborator->role));
        } elseif ($collaborator->wasChanged('role')) {
            $target->notify(new DiagramCollaboratorRoleChanged($diagram, $collaborator->role));
        }

        return $collaborator;
    }

    /**
     * Self-request: stays pending until the Diagram Owner decides, and never
     * downgrades someone already approved.
     * Repeating a pending request doesn't notify the Diagram Owner again.
     */
    public function requestAccess(Diagram $diagram, User $requester): Collaborator
    {
        $existing = $this->collaborators->findFor($diagram, $requester);

        if ($existing?->status === Collaborator::STATUS_APPROVED) {
            return $existing;
        }

        $wasRequested = $existing?->status === Collaborator::STATUS_REQUESTED;

        $collaborator = $this->collaborators->upsertStatus($diagram, $requester, Collaborator::STATUS_REQUESTED);

        if (! $wasRequested) {
            $diagram->owner->notify(new DiagramAccessRequested($diagram, $requester));
        }

        return $collaborator;
    }

    /**
     * Approves a pending request, telling the requester. Approving someone
     * already approved changes nothing and notifies no one — unless a $role
     * is given and differs, which is a role change.
     */
    public function approve(Diagram $diagram, User $user, ?string $role = null): Collaborator
    {
        $wasPending = $this->collaborators->findForOrFail($diagram, $user)->status === Collaborator::STATUS_REQUESTED;
        $collaborator = $this->collaborators->upsertApproved($diagram, $user, $role);

        if ($wasPending) {
            $user->notify(new DiagramAccessRequestResolved($diagram, approved: true));
        } elseif ($collaborator->wasChanged('role')) {
            $user->notify(new DiagramCollaboratorRoleChanged($diagram, $collaborator->role));
        }

        return $collaborator;
    }

    /**
     * Declines a pending request or removes an approved Collaborator, telling
     * the User which of the two happened. Nothing to remove is not an error.
     */
    public function revoke(Diagram $diagram, User $user): void
    {
        $removed = $this->collaborators->removeFor($diagram, $user);

        if ($removed === null) {
            return;
        }

        $this->locks->reconcile($diagram);

        $user->notify($removed->status === Collaborator::STATUS_REQUESTED
            ? new DiagramAccessRequestResolved($diagram, approved: false)
            : new DiagramCollaboratorRemoved($diagram));
    }
}
