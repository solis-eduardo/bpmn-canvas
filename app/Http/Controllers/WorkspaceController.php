<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Repositories\WorkspaceRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
    ) {}

    /**
     * Lists the Workspaces the current User is a Member of (CONTEXT.md,
     * "Member"). An Administrator sees every Workspace instead, since they
     * "can manage any Workspace" (CONTEXT.md, "Administrator").
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $workspaces = $user->is_admin
            ? $this->workspaces->allWithOwnerAndMembers()
            : $this->workspaces->forUserWithOwnerAndMembers($user);

        return view('workspaces.index', compact('workspaces'));
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
     * Links or unlinks a Workspace's Keycloak group. Administrators only.
     */
    public function updateKeycloakGroup(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageKeycloakGroup', $workspace);

        $data = $request->validate([
            'keycloak_group' => ['nullable', 'string', 'max:255'],
        ]);

        $workspace->update(['keycloak_group' => $data['keycloak_group'] ?? null]);

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
