<?php

namespace App\Repositories;

use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use App\Support\AccessMemo;

/**
 * Persistence for a Workspace's Members. Deciding who gets notified about a
 * change is the caller's job, not this class's.
 */
class MemberRepository
{
    public function findFor(Workspace $workspace, User $user): ?Member
    {
        return Member::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function existsFor(Workspace $workspace, User $user): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Adds (or re-marks as manual) the User's membership. Check
     * `wasRecentlyCreated` on the result to tell a new Member from an
     * existing one.
     */
    public function upsertManual(Workspace $workspace, User $user): Member
    {
        return $workspace->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['origin' => Member::ORIGIN_MANUAL],
        );
    }

    /**
     * Adds a group-managed Member without changing any existing grant: an
     * existing Member is returned untouched.
     */
    public function createGroupIfMissing(Workspace $workspace, User $user): Member
    {
        return $this->findFor($workspace, $user)
            ?? $workspace->members()->create([
                'user_id' => $user->id,
                'origin' => Member::ORIGIN_GROUP,
            ]);
    }

    public function removeFor(Workspace $workspace, User $user): bool
    {
        $removed = $workspace->members()->where('user_id', $user->id)->delete() > 0;

        app(AccessMemo::class)->forget();

        return $removed;
    }

    /**
     * Drops the User's group-managed memberships in Workspaces not linked
     * to any of $groups. Manual memberships are never touched.
     *
     * @param  list<string>  $groups
     */
    public function removeGroupMembershipsOutside(User $user, array $groups): int
    {
        $removed = Member::where('user_id', $user->id)
            ->where('origin', Member::ORIGIN_GROUP)
            ->whereDoesntHave('workspace', fn ($query) => $query->whereIn('keycloak_group', $groups))
            ->delete();

        app(AccessMemo::class)->forget();

        return $removed;
    }
}
