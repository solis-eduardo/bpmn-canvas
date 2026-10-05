<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\ListSort;
use App\Repositories\UserRepository;
use App\Repositories\WorkspaceListFilters;
use App\Repositories\WorkspaceRepository;
use App\Services\MemberService;
use App\Services\WorkspaceOwnerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    use RedirectsAfterSave;

    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly UserRepository $users,
        private readonly MemberService $members,
        private readonly WorkspaceOwnerService $owners,
    ) {}

    /**
     * Lists the Workspaces the current User is a Member of (CONTEXT.md,
     * "Member"). An Administrator sees every Workspace instead, since they
     * "can manage any Workspace" (CONTEXT.md, "Administrator").
     *
     * Paginated, and narrowed by the "name", "owner" and "member"
     * query filters (issues #127, #148) — the last two pick an existing User
     * — which are kept on the pagination links.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = WorkspaceListFilters::fromRequest($request);
        $sort = ListSort::fromRequest($request, WorkspaceRepository::SORT_COLUMNS);

        $workspaces = $this->workspaces
            ->search($user, $filters, $sort)
            ->appends($request->query());

        $users = $this->asOptions($this->users->allOrderedByName());

        return view('workspaces.index', compact('workspaces', 'filters', 'users', 'sort'));
    }

    public function create(Request $request): View
    {
        return view('workspaces.create', [
            'memberOptions' => $this->userOptions($this->users->allOrderedByName(), $request->user()),
        ]);
    }

    /**
     * Existing Users a Workspace Owner/Administrator may pick from instead of
     * typing an email: Users already in the target role are left out.
     *
     * @param  Collection<int, User>  $candidates
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function asOptions(Collection $candidates): array
    {
        return $candidates
            ->map(fn (User $candidate) => ['id' => $candidate->id, 'name' => $candidate->name, 'email' => $candidate->email])
            ->values()
            ->all();
    }

    /**
     * Any User can create a Workspace and becomes its Workspace Owner (and a
     * Member) (CONTEXT.md, "Workspace", "Workspace Owner").
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        if ($request->has('keycloak_group')) {
            $this->authorize('manageKeycloakGroup', new Workspace);
        }

        $workspace = $this->workspaces->create($data['name'], $request->user(), $data['keycloak_group'] ?? null);

        // The creator is already a Member; the picked Users are added with their notification.
        $this->users->findMany(collect($data['members'] ?? [])->unique()->reject(fn ($id) => (int) $id === $request->user()->id)->all())
            ->each(fn (User $user) => $this->members->add($workspace, $user));

        return $this->afterSave($request, 'workspaces.index', 'workspaces.edit', [$workspace]);
    }

    /**
     * The Workspace form: rename, Keycloak link (Administrators), Members and
     * ownership transfer (issue #148).
     */
    public function edit(Workspace $workspace): View
    {
        $this->authorize('update', $workspace);

        $workspace->load('members.user');

        $everyone = $this->users->allOrderedByName();
        $memberIds = $workspace->members->pluck('user_id');

        $memberOptions = $this->asOptions($everyone);
        $ownerCandidates = $this->asOptions($everyone->reject(fn (User $candidate) => $workspace->isOwnedBy($candidate)));

        return view('workspaces.edit', compact('workspace', 'memberOptions', 'memberIds', 'ownerCandidates'));
    }

    /**
     * Name and Keycloak group path, the two fields of the Workspace form. The
     * Keycloak group is part of the record but only Administrators may set it
     * (CONTEXT.md, "Workspace").
     *
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'keycloak_group' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Updates a Workspace (rename, and the Keycloak group link for
     * Administrators): Workspace Owner or Administrator only.
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        // The whole Workspace form in one request: data, Members (the picker's
        // chips) and, optionally, the new Workspace Owner.
        $data = $request->validate($this->rules() + [
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $newOwnerId = $this->requestedNewOwnerId($data, $workspace);
        $wanted = $request->has('members') ? $this->wantedMemberIds($data, $workspace, $newOwnerId) : null;

        if ($request->has('keycloak_group')) {
            $this->authorize('manageKeycloakGroup', $workspace);
            $this->workspaces->linkKeycloakGroup($workspace, $data['keycloak_group'] ?? null);
        }

        $workspace->update(['name' => $data['name']]);

        if ($wanted !== null) {
            $this->syncMembers($workspace, $wanted);
        }

        if ($newOwnerId !== null) {
            $this->owners->transfer($workspace, $this->users->findOrFail($newOwnerId));

            // The previous Owner may no longer be allowed on this form.
            return redirect()->route('workspaces.index')->with('status', __('messages.saved'));
        }

        return $this->afterSave($request, 'workspaces.index', 'workspaces.edit', [$workspace]);
    }

    /**
     * The User picked to become Workspace Owner, if any: only who may transfer
     * the Workspace can ask, and it can't be its current Owner.
     */
    private function requestedNewOwnerId(array $data, Workspace $workspace): ?int
    {
        if (! filled($data['user_id'] ?? null)) {
            return null;
        }

        $this->authorize('workspace-owner.transfer', $workspace);

        if ((int) $data['user_id'] === $workspace->owner_id) {
            throw ValidationException::withMessages(['user_id' => __('messages.user_already_owner')]);
        }

        return (int) $data['user_id'];
    }

    /**
     * The ids the Members picker asks for. The Workspace Owner can't be
     * dropped; a transfer keeps the previous Owner as a Member and makes the
     * new one a Member.
     *
     * @return SupportCollection<int, int>
     */
    private function wantedMemberIds(array $data, Workspace $workspace, ?int $newOwnerId): SupportCollection
    {
        $this->authorize('member.add', $workspace);

        $wanted = collect($data['members'] ?? [])->map(fn ($id) => (int) $id)->unique();

        if ($newOwnerId !== null) {
            return $wanted->push($workspace->owner_id, $newOwnerId)->unique();
        }

        if (! $wanted->contains($workspace->owner_id)) {
            throw ValidationException::withMessages(['members' => __('messages.cannot_remove_workspace_owner')]);
        }

        return $wanted;
    }

    /**
     * Adds the new Members and removes the ones no longer wanted, each with
     * its notification.
     *
     * @param  SupportCollection<int, int>  $wanted
     */
    private function syncMembers(Workspace $workspace, SupportCollection $wanted): void
    {
        $current = $workspace->members()->pluck('user_id');

        $this->users->findMany($wanted->diff($current)->all())
            ->each(fn (User $user) => $this->members->add($workspace, $user));

        $this->users->findMany($current->diff($wanted)->all())
            ->each(fn (User $user) => $this->members->remove($workspace, $user));
    }

    /**
     * Deletes a Workspace: Workspace Owner or Administrator only. Refused
     * while the Workspace still holds Diagrams (CONTEXT.md, "Workspace
     * Owner") — allowed once it's empty.
     */
    public function destroy(Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        if ($workspace->hasDiagrams()) {
            abort(422, __('messages.cannot_delete_workspace_with_diagrams'));
        }

        $workspace->delete();

        return redirect()->route('workspaces.index');
    }
}
