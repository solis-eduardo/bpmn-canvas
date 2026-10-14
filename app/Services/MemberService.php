<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use App\Repositories\MemberRepository;
use App\Repositories\WorkspaceRepository;
use Illuminate\Support\Facades\DB;

/**
 * A Workspace's Members and the notification each change deserves. Whether
 * the caller may do any of this — including "the Workspace Owner can't be
 * removed" — is MemberPolicy's call.
 */
class MemberService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly WorkspaceRepository $workspaces,
        private readonly LockService $locks,
    ) {}

    /**
     * Adds the User as a manual Member, telling them only when they
     * weren't one already.
     */
    public function add(Workspace $workspace, User $user): Member
    {
        $member = $this->members->upsertManual($workspace, $user);

        if ($member->wasRecentlyCreated) {
            $user->notify(new WorkspaceMemberAdded($workspace));
        }

        return $member;
    }

    /**
     * Removes the User, telling them only when they were a Member.
     */
    public function remove(Workspace $workspace, User $user): bool
    {
        $removed = $this->members->removeFor($workspace, $user);

        if ($removed) {
            // A Lock the User holds stays only if they can still edit the
            // Diagram another way (Diagram Owner or Collaborator).
            $this->locks->reconcileHeldBy($user, $workspace);

            $user->notify(new WorkspaceMemberRemoved($workspace));
        }

        return $removed;
    }

    public function leave(Workspace $workspace, User $user): bool
    {
        return $this->remove($workspace, $user);
    }

    /**
     * Reconciles only group-managed memberships; manual grants are never
     * changed, even when they overlap with the User's Keycloak groups.
     *
     * @param  list<string>  $groups
     */
    public function syncFromKeycloakGroups(User $user, array $groups): void
    {
        DB::transaction(function () use ($user, $groups) {
            $removed = $this->members->removeGroupMembershipsOutside($user, $groups);

            $this->workspaces->linkedToKeycloakGroups($groups)
                ->each(function (Workspace $workspace) use ($user) {
                    if ($this->members->createGroupIfMissing($workspace, $user)->wasRecentlyCreated) {
                        $user->notify(new WorkspaceMemberAdded($workspace));
                    }
                });

            // Only dropped group memberships can take edit rights away from a holder.
            if ($removed > 0) {
                $this->locks->reconcileHeldBy($user);
            }
        });
    }
}
