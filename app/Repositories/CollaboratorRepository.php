<?php

namespace App\Repositories;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Persistence for a Diagram's Collaborators. Deciding who gets notified
 * about a change is the caller's job, not this class's.
 */
class CollaboratorRepository
{
    public function findFor(Diagram $diagram, User $user): ?Collaborator
    {
        return Collaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function findForOrFail(Diagram $diagram, User $user): Collaborator
    {
        return Collaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Collaborator>
     */
    public function listWithUser(Diagram $diagram): Collection
    {
        return $diagram->collaborators()->with('user')->get();
    }

    public function upsertApproved(Diagram $diagram, User $user): Collaborator
    {
        return $this->upsertStatus($diagram, $user, Collaborator::STATUS_APPROVED);
    }

    public function upsertStatus(Diagram $diagram, User $user, string $status): Collaborator
    {
        return Collaborator::updateOrCreate(
            ['diagram_id' => $diagram->id, 'user_id' => $user->id],
            ['status' => $status],
        );
    }

    /**
     * Deletes the User's Collaborator row and returns it (still carrying the
     * status it had), or null if there was none.
     */
    public function removeFor(Diagram $diagram, User $user): ?Collaborator
    {
        $collaborator = $this->findFor($diagram, $user);

        $collaborator?->delete();

        return $collaborator;
    }
}
