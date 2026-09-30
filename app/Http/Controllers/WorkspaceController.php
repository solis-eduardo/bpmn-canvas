<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\TransferOwnership;
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

        $workspace->addMember($request->user(), notify: false);

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
     * Transfers the Workspace Owner role to another User: current Workspace
     * Owner or Administrator only (CONTEXT.md, "Workspace Owner"; ADR-0006;
     * issue #29). No acceptance step — takes effect immediately, and the
     * recipient becomes a Member too if they weren't one already.
     *
     * Returns silently regardless of whether the email matches an existing
     * User (avoiding email enumeration), same as WorkspaceMemberController.
     */
    public function transferOwnership(Request $request, Workspace $workspace, TransferOwnership $transfer): RedirectResponse
    {
        $this->authorize('transferOwnership', $workspace);

        $data = $request->validate($this->userIdentifierRules());

        $newOwner = $this->resolveUserFrom($data);

        if ($newOwner) {
            $transfer($workspace, $newOwner);
        }

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
