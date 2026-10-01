<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

class SyncKeycloakGroupMemberships
{
    /**
     * Reconciles only group-managed memberships; manual grants are never
     * changed, even when they overlap with the User's Keycloak groups.
     *
     * @param  list<string>  $groups
     */
    public function __invoke(User $user, array $groups): void
    {
        DB::transaction(function () use ($user, $groups) {
            WorkspaceMember::where('user_id', $user->id)
                ->where('origin', WorkspaceMember::ORIGIN_GROUP)
                ->whereDoesntHave('workspace', fn ($query) => $query->whereIn('keycloak_group', $groups))
                ->delete();

            Workspace::whereIn('keycloak_group', $groups)
                ->get()
                ->each(fn (Workspace $workspace) => $workspace->addGroupMember($user));
        });
    }
}
