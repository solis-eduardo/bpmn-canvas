<?php

namespace App\Repositories;

use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceRepository
{
    public function __construct(private readonly MemberRepository $members) {}

    public function find(int $id): ?Workspace
    {
        return Workspace::find($id);
    }

    public function findOrFail(int $id): Workspace
    {
        return Workspace::findOrFail($id);
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function allOrderedByName(): Collection
    {
        return Workspace::orderBy('name')->get();
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function forUserOrderedByName(User $user): Collection
    {
        return $user->workspaces()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function allWithOwnerAndMembers(): Collection
    {
        return Workspace::with(['owner', 'members.user'])->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function forUserWithOwnerAndMembers(User $user): Collection
    {
        return $user->workspaces()->with(['owner', 'members.user'])->orderBy('name')->get();
    }

    /**
     * @param  list<string>  $groups
     * @return Collection<int, Workspace>
     */
    public function linkedToKeycloakGroups(array $groups): Collection
    {
        return Workspace::whereIn('keycloak_group', $groups)->get();
    }

    /**
     * Creates the Workspace owned by $owner and adds them as its first
     * Member, atomically: a Workspace never exists without its Owner being
     * a Member.
     */
    public function create(string $name, User $owner): Workspace
    {
        return DB::transaction(function () use ($name, $owner) {
            $workspace = Workspace::create(['name' => $name, 'owner_id' => $owner->id]);

            $this->members->upsertManual($workspace, $owner);

            return $workspace;
        });
    }

    public function addMember(Workspace $workspace, User $user): Member
    {
        return $this->members->upsertManual($workspace, $user);
    }

    public function removeMember(Workspace $workspace, User $user): bool
    {
        return $this->members->removeFor($workspace, $user);
    }

    public function hasMember(Workspace $workspace, User $user): bool
    {
        return $this->members->existsFor($workspace, $user);
    }
}
