<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceOwnershipTransferred;
use App\Repositories\WorkspaceRepository;
use Illuminate\Support\Facades\DB;

/**
 * Transfers the Workspace Owner role to another User, with no acceptance
 * step (CONTEXT.md, "Workspace Owner"; ADR-0006; issue #29). Authorization
 * (current Workspace Owner or Administrator) is the caller's responsibility
 * — this Action only encodes what happens on transfer.
 *
 * The recipient becomes Workspace Owner immediately and, if not already a
 * Member, is added as one — mirroring how a Diagram's new Owner always has
 * full access (see App\Actions\Diagrams\TransferOwnership). Unlike a
 * Diagram Owner transfer, the previous Workspace Owner isn't touched: they
 * stay a Member (they already were one, being the previous Owner).
 */
class TransferOwnership
{
    public function __construct(private readonly WorkspaceRepository $workspaces) {}

    public function __invoke(Workspace $workspace, User $newOwner): void
    {
        DB::transaction(function () use ($workspace, $newOwner) {
            $this->workspaces->addMember($workspace, $newOwner);

            $workspace->update(['owner_id' => $newOwner->id]);
        });

        $newOwner->notify(new WorkspaceOwnershipTransferred($workspace));
    }
}
