<?php

namespace App\Services\Identity;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DiagramOwnerService;
use App\Services\LockService;
use App\Services\WorkspaceOwnerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates and reactivates a User account, and hands their owned Diagrams
 * and Workspaces to a successor (CONTEXT.md, "User"; ADR-0020). Who may do
 * this is the Administrator-only route's call, not this service's.
 */
class UserDeactivationService
{
    public function __construct(
        private readonly UserSessionRevoker $sessionRevoker,
        private readonly DiagramOwnerService $diagramOwners,
        private readonly WorkspaceOwnerService $workspaceOwners,
        private readonly LockService $locks,
    ) {}

    /**
     * Blocks both login methods, releases the User's Locks (keeping their
     * unsaved content as provisional Versions) and ends their sessions. Their
     * authorship and any Diagrams or Workspaces they own are left as they are.
     */
    public function deactivate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['deactivated_at' => now()])->save();

            Diagram::query()
                ->where('locked_by', $user->getKey())
                ->get()
                ->each(fn (Diagram $diagram) => $this->locks->release($diagram, saveDraft: true));
        });

        $this->sessionRevoker->revoke($user);
    }

    public function reactivate(User $user): void
    {
        $user->forceFill(['deactivated_at' => null])->save();
    }

    /**
     * Hands every Diagram and Workspace the User owns to $successor, in one
     * transaction. The previous Diagram Owner loses their access entirely
     * rather than being left behind as a Collaborator.
     */
    public function transferOwnership(User $from, User $successor): void
    {
        DB::transaction(function () use ($from, $successor): void {
            $this->ownedDiagrams($from)->each(
                fn (Diagram $diagram) => $this->diagramOwners->transfer($diagram, $successor, giveUpAccess: true),
            );

            $this->ownedWorkspaces($from)->each(
                fn (Workspace $workspace) => $this->workspaceOwners->transfer($workspace, $successor),
            );
        });
    }

    /**
     * @return Collection<int, Diagram>
     */
    public function ownedDiagrams(User $user): Collection
    {
        return Diagram::query()->where('owner_id', $user->getKey())->orderBy('title')->get();
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function ownedWorkspaces(User $user): Collection
    {
        return Workspace::query()->where('owner_id', $user->getKey())->orderBy('name')->get();
    }
}
