<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    /**
     * Sortable columns of the User list (header clicks) with their default direction.
     *
     * @var array<string, string>
     */
    public const SORT_COLUMNS = ['name' => 'asc', 'updated_at' => 'desc'];

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public function findMany(array $ids): Collection
    {
        return User::findMany($ids);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->byEmail($email)->first();
    }

    /**
     * A local account with this email that isn't linked to a Keycloak
     * identity yet.
     */
    public function findUnlinkedByEmail(string $email): ?User
    {
        return $this->byEmail($email)->whereNull('keycloak_id')->first();
    }

    /**
     * @return Builder<User>
     */
    private function byEmail(string $email): Builder
    {
        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
    }

    public function findByKeycloakId(string $keycloakId): ?User
    {
        return User::where('keycloak_id', $keycloakId)->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function allOrderedByName(): Collection
    {
        return User::orderBy('name')->get();
    }

    /**
     * One page of Users ordered by $sort, narrowed by $filters.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function search(UserListFilters $filters, ListSort $sort): LengthAwarePaginator
    {
        return User::query()
            ->when($filters->name !== '', function (Builder $q) use ($filters) {
                $like = '%'.$filters->name.'%';

                $q->where(fn (Builder $n) => $n->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filters->loginMethod !== null, fn (Builder $q) => $q->where('login_method', $filters->loginMethod))
            ->when($filters->admin !== null, fn (Builder $q) => $q->where('is_admin', $filters->admin))
            ->orderBy($sort->column, $sort->direction)
            ->orderBy('id')
            ->paginate(20);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Ends every database session of the User except the one identified by
     * $currentSessionId. Returns how many sessions were ended.
     */
    public function invalidateOtherSessions(User $user, string $currentSessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
