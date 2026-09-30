<?php

namespace App\Actions\Diagrams;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use App\Notifications\DiagramOwnershipTransferred;
use Illuminate\Support\Facades\DB;

/**
 * Transfers the Diagram Owner role to another User, with no acceptance step
 * (CONTEXT.md, "Diagram Owner"; ADR-0006; issue #14). Authorization
 * (current Diagram Owner or Administrator) is the caller's responsibility —
 * this Action only encodes what happens to the previous Diagram Owner once
 * the transfer is allowed.
 *
 * In the same transaction as the ownership change: by default the previous
 * Diagram Owner becomes a Collaborator (status "approved"); when
 * $giveUpAccess is true, they get no access instead (any existing
 * Collaborator row for them is removed). The Author is never touched — it's
 * fixed forever, regardless of who owns the Diagram.
 */
class TransferOwnership
{
    public function __invoke(Diagram $diagram, User $newOwner, bool $giveUpAccess = false): void
    {
        $previousOwnerId = $diagram->owner_id;

        DB::transaction(function () use ($diagram, $newOwner, $giveUpAccess, $previousOwnerId) {
            $diagram->update(['owner_id' => $newOwner->id]);

            // The new Diagram Owner has full access already — drop any
            // leftover Collaborator row for them so they're not listed
            // twice (as Owner and as Collaborator).
            DiagramCollaborator::where('diagram_id', $diagram->id)
                ->where('user_id', $newOwner->id)
                ->delete();

            if ((int) $previousOwnerId === $newOwner->id) {
                return;
            }

            if ($giveUpAccess) {
                DiagramCollaborator::where('diagram_id', $diagram->id)
                    ->where('user_id', $previousOwnerId)
                    ->delete();

                return;
            }

            DiagramCollaborator::updateOrCreate(
                ['diagram_id' => $diagram->id, 'user_id' => $previousOwnerId],
                ['status' => DiagramCollaborator::STATUS_APPROVED]
            );
        });

        if ((int) $previousOwnerId !== $newOwner->id) {
            $newOwner->notify(new DiagramOwnershipTransferred($diagram));
        }
    }
}
