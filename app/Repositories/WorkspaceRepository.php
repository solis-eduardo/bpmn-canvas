<?php

namespace App\Repositories;

use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
     * One page of Workspaces, by name, with owner and Members loaded: every
     * Workspace for an Administrator, otherwise only those $user is
     * a Member of; narrowed by $filters.
     *
     * @return LengthAwarePaginator<int, Workspace>
     */
    public function search(User $user, WorkspaceListFilters $filters): LengthAwarePaginator
    {
        $query = $user->is_admin ? Workspace::query() : $user->workspaces();

        return $query
            ->with(['owner', 'members.user'])
            ->when($filters->name !== '', fn (Builder $q) => $q->where('workspaces.name', 'like', self::like($filters->name)))
            ->when($filters->owner !== '', fn (Builder $q) => $q->whereHas('owner', fn (Builder $u) => self::matchUser($u, $filters->owner)))
            ->when($filters->member !== '', fn (Builder $q) => $q->whereHas('members.user', fn (Builder $u) => self::matchUser($u, $filters->member)))
            ->when($filters->diagram !== '', fn (Builder $q) => $q->whereHas('diagrams', fn (Builder $d) => $d
                ->where(fn (Builder $w) => $w
                    ->where('title', 'like', self::like($filters->diagram))
                    ->orWhere('slug', 'like', self::like($filters->diagram)))))
            ->orderBy('workspaces.name')
            ->orderBy('workspaces.id')
            ->paginate(20);
    }

    /**
     * Workspaces whose name contains $term, by name: every Workspace for an
     * Administrator, otherwise only those $user is a Member of. Returns the
     * first $limit plus the total number of matches.
     *
     * @return array{items: Collection<int, Workspace>, total: int}
     */
    public function matchingName(User $user, string $term, int $limit): array
    {
        $query = ($user->is_admin ? Workspace::query() : $user->workspaces())
            ->where('workspaces.name', 'like', self::like($term));

        return [
            'items' => (clone $query)->orderBy('workspaces.name')->orderBy('workspaces.id')->limit($limit)->get(),
            'total' => $query->count(),
        ];
    }

    private static function matchUser(Builder $users, string $term): void
    {
        $users->where(fn (Builder $w) => $w
            ->where('name', 'like', self::like($term))
            ->orWhere('email', 'like', self::like($term)));
    }

    private static function like(string $term): string
    {
        return '%'.$term.'%';
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

    public function changeOwner(Workspace $workspace, User $newOwner): Workspace
    {
        $workspace->update(['owner_id' => $newOwner->id]);

        return $workspace;
    }

    public function linkKeycloakGroup(Workspace $workspace, ?string $group): void
    {
        $workspace->update(['keycloak_group' => $group]);
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
