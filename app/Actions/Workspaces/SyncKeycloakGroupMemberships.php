<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAdded;
use App\Repositories\MemberRepository;
use App\Repositories\WorkspaceRepository;
use Illuminate\Support\Facades\DB;

class SyncKeycloakGroupMemberships
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly WorkspaceRepository $workspaces,
    ) {}

    /**
     * Reconciles only group-managed memberships; manual grants are never
     * changed, even when they overlap with the User's Keycloak groups.
     *
     * @param  list<string>  $groups
     */
    public function __invoke(User $user, array $groups): void
    {
        DB::transaction(function () use ($user, $groups) {
            $this->members->removeGroupMembershipsOutside($user, $groups);

            $this->workspaces->linkedToKeycloakGroups($groups)
                ->each(function (Workspace $workspace) use ($user) {
                    if ($this->members->createGroupIfMissing($workspace, $user)->wasRecentlyCreated) {
                        $user->notify(new WorkspaceMemberAdded($workspace));
                    }
                });
        });
    }
}
