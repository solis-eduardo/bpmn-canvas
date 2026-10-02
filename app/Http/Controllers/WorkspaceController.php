<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Repositories\UserRepository;
use App\Repositories\WorkspaceListFilters;
use App\Repositories\WorkspaceRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly UserRepository $users,
    ) {}

    /**
     * Lists the Workspaces the current User is a Member of (CONTEXT.md,
     * "Member"). An Administrator sees every Workspace instead, since they
     * "can manage any Workspace" (CONTEXT.md, "Administrator").
     *
     * Paginated, and narrowed by the "name", "owner", "member" and "diagram"
     * query filters (issue #127), which are kept on the pagination links.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = WorkspaceListFilters::fromRequest($request);

        $workspaces = $this->workspaces
            ->search($user, $filters)
            ->appends($request->query());

        $memberCandidates = [];
        $ownerCandidates = [];

        if ($workspaces->contains(fn (Workspace $workspace) => $workspace->isManagedBy($user))) {
            $everyone = $this->users->allOrderedByName();

            foreach ($workspaces as $workspace) {
                if (! $workspace->isManagedBy($user)) {
                    continue;
                }

                $memberIds = $workspace->members->pluck('user_id');

                $memberCandidates[$workspace->id] = $this->asOptions($everyone->reject(fn (User $candidate) => $memberIds->contains($candidate->id)));
                $ownerCandidates[$workspace->id] = $this->asOptions($everyone->reject(fn (User $candidate) => $workspace->isOwnedBy($candidate)));
            }
        }

        return view('workspaces.index', compact('workspaces', 'filters', 'memberCandidates', 'ownerCandidates'));
    }

    public function create(): View
    {
        return view('workspaces.create');
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->workspaces->create($data['name'], $request->user());

        return redirect()->route('workspaces.index');
    }

    public function edit(Workspace $workspace): View
    {
        $this->authorize('update', $workspace);

        return view('workspaces.edit', compact('workspace'));
    }

    /**
     * Renames a Workspace: Workspace Owner or Administrator only.
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace->update(['name' => $data['name']]);

        return redirect()->route('workspaces.index');
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
