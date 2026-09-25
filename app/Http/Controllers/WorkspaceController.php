<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    /**
     * Users see the list of Workspaces they're a Member of (CONTEXT.md,
     * "Member") — not every Workspace in the system. An Administrator still
     * uses this same list for their own memberships; managing *any*
     * Workspace (ADR-0009) happens from that Workspace's own show page,
     * reached once they know its id.
     */
    public function index(Request $request): View
    {
        $workspaces = $request->user()->workspaces()->with('owner')->orderBy('name')->get();

        return view('workspaces.index', compact('workspaces'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Workspace::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = Workspace::create([
            'name' => $data['name'],
            'owner_id' => $request->user()->id,
        ]);

        // The creator becomes the Workspace Owner and, per CONTEXT.md
        // ("Workspace"), a Member as well — see ADR-0009.
        $workspace->addMember($request->user());

        return redirect()->route('workspaces.show', $workspace);
    }

    public function show(Workspace $workspace): View
    {
        $workspace->load(['owner', 'members']);

        $this->authorize('view', $workspace);

        return view('workspaces.show', compact('workspace'));
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace->update(['name' => $data['name']]);

        return redirect()->route('workspaces.show', $workspace);
    }
}
