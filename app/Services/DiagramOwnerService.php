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

            if ($giveUpAccess) {
                $this->collaborators->removeFor($diagram, $previousOwner);

                return;
            }

            $this->collaborators->upsertApproved($diagram, $previousOwner, Collaborator::ROLE_COLLABORATOR);
        });

        if ($changesHands) {
            $newOwner->notify(new DiagramOwnershipTransferred($diagram));
        }
    }
}
