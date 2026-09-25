<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    /**
     * Lists the Workspaces the current User is a Member of (CONTEXT.md,
     * "Member"). An Administrator sees every Workspace instead, since they
     * "can manage any Workspace" (CONTEXT.md, "Administrator").
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $workspaces = $user->is_admin
            ? Workspace::with(['owner', 'members.user'])->orderBy('name')->get()
            : $user->workspaces()->with(['owner', 'members.user'])->orderBy('name')->get();

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

        $workspace = Workspace::create([
            'name' => $data['name'],
            'owner_id' => $request->user()->id,
        ]);

        $workspace->addMember($request->user());

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
}
