<?php

namespace App\Services;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Notifications\DiagramOwnershipTransferred;
use App\Repositories\CollaboratorRepository;
use App\Repositories\DiagramRepository;
use Illuminate\Support\Facades\DB;

/**
 * Transfers the Diagram Owner role, with no acceptance step (CONTEXT.md,
 * "Diagram Owner"; ADR-0006). Who may transfer is DiagramOwnerPolicy's call.
 */
class DiagramOwnerService
{
    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly CollaboratorRepository $collaborators,
        private readonly LockService $locks,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * In one transaction: the new Diagram Owner takes over, and by default
     * the previous one becomes a Collaborator — or, with $giveUpAccess, ends
     * up with no access at all. The Author is never touched.
     */
    public function transfer(Diagram $diagram, User $newOwner, bool $giveUpAccess = false): void
    {
        $changesHands = ! $diagram->isOwnedBy($newOwner);
        $previousOwner = $changesHands ? $diagram->owner : null;

        DB::transaction(function () use ($diagram, $newOwner, $giveUpAccess, $previousOwner) {
            $this->diagrams->changeOwner($diagram, $newOwner);

            // The new Diagram Owner has full access already: drop any
            // leftover Collaborator row so they aren't listed twice.
            $this->collaborators->removeFor($diagram, $newOwner);

            if ($previousOwner === null) {
                return;
            }

            $this->settlePreviousOwner($diagram, $previousOwner, $giveUpAccess);

            $this->activity->record(
                'diagram.owner_transferred',
                $newOwner,
                diagram: $diagram,
                before: ['diagram_owner' => $previousOwner->name],
                after: [
                    'diagram_owner' => $newOwner->name,
                    'previous_owner_access' => $giveUpAccess ? 'none' : Collaborator::ROLE_COLLABORATOR,
                ],
            );

            // A previous Diagram Owner who gave up access loses edit rights
            // here, and the Lock follows, as for any other access change.
            $this->locks->reconcile($diagram);
        });

        if ($changesHands) {
            // Held back until the outermost transaction commits, so a
            // rolled-back submission never tells the new owner about a transfer.
            DB::afterCommit(fn () => $newOwner->notify(new DiagramOwnershipTransferred($diagram)));
        }
    }

    /**
     * The previous Diagram Owner keeps access as a Collaborator, or loses it
     * entirely when $giveUpAccess is set.
     */
    private function settlePreviousOwner(Diagram $diagram, User $previousOwner, bool $giveUpAccess): void
    {
        if ($giveUpAccess) {
            $this->collaborators->removeFor($diagram, $previousOwner);

            return;
        }

        $this->collaborators->upsertApproved($diagram, $previousOwner, Collaborator::ROLE_COLLABORATOR);
    }
}
