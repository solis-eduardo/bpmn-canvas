<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Repositories\WorkspaceRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceKeycloakGroupController extends Controller
{
    public function __construct(private readonly WorkspaceRepository $workspaces) {}

    /**
     * Links or unlinks a Workspace's Keycloak group. Administrators only.
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageKeycloakGroup', $workspace);

        $data = $request->validate([
            'keycloak_group' => ['nullable', 'string', 'max:255'],
        ]);

        $this->workspaces->linkKeycloakGroup($workspace, $data['keycloak_group'] ?? null);

        return redirect()->route('workspaces.index');
    }
}
