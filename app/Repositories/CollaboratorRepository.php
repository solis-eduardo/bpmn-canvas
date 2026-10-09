<?php

namespace App\Repositories;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Persistence for a Diagram's Collaborators. Deciding who gets notified
 * about a change is the caller's job, not this class's.
 */
class CollaboratorRepository
{
    public function findFor(Diagram $diagram, User $user): ?Collaborator
    {
        return $this->rowFor($diagram, $user)->first();
    }

    public function findForOrFail(Diagram $diagram, User $user): Collaborator
    {
        return $this->rowFor($diagram, $user)->firstOrFail();
    }

    /**
     * @return Collection<int, Collaborator>
     */
    public function listWithUser(Diagram $diagram): Collection
    {
        return $diagram->collaborators()->with('user')->get();
    }

    /**
     * A null $role leaves an existing row's role alone (a new row is a plain
     * Collaborator).
     */
    public function upsertApproved(Diagram $diagram, User $user, ?string $role = null): Collaborator
    {
        return $this->upsertStatus($diagram, $user, Collaborator::STATUS_APPROVED, $role);
    }

    public function approve(Collaborator $collaborator): Collaborator
    {
        $collaborator->update(['status' => Collaborator::STATUS_APPROVED]);

        return $collaborator;
    }

    public function upsertStatus(Diagram $diagram, User $user, string $status, ?string $role = null): Collaborator
    {
        return Collaborator::updateOrCreate(
            ['diagram_id' => $diagram->id, 'user_id' => $user->id],
            $role === null ? ['status' => $status] : ['status' => $status, 'role' => $role],
        );
    }

    public function deleteFor(Diagram $diagram, User $user): void
    {
        $this->rowFor($diagram, $user)->delete();
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

    /**
     * @return Builder<Collaborator>
     */
    private function rowFor(Diagram $diagram, User $user): Builder
    {
        return Collaborator::where('diagram_id', $diagram->id)->where('user_id', $user->id);
    }
}
