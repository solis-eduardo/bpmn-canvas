<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceOwnershipTransferred;
use App\Repositories\WorkspaceRepository;
use Illuminate\Support\Facades\DB;

/**
 * Transfers the Workspace Owner role, with no acceptance step (CONTEXT.md,
 * "Workspace Owner"; ADR-0006). Who may transfer is WorkspaceOwnerPolicy's
 * call.
 */
class WorkspaceOwnerService
{
    public function __construct(private readonly WorkspaceRepository $workspaces) {}

    /**
     * The recipient becomes Workspace Owner and, if not already, a Member.
     * The previous Workspace Owner stays a Member.
     */
    public function transfer(Workspace $workspace, User $newOwner): void
    {
        DB::transaction(function () use ($workspace, $newOwner) {
            $this->workspaces->addMember($workspace, $newOwner);

            $this->workspaces->changeOwner($workspace, $newOwner);
        });

        $newOwner->notify(new WorkspaceOwnershipTransferred($workspace));
    }
}
