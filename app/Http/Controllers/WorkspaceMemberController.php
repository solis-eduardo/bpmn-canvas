<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    /**
     * Workspace Owner or Administrator adds a Member by email
     * (CONTEXT.md, "Member" — "Members are added by the Workspace Owner").
     */
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $target = User::where('email', $data['email'])->first();

        if (! $target) {
            return back()->withErrors(['email' => __('messages.workspace_member_not_found')]);
        }

        $workspace->addMember($target);

        return redirect()->route('workspaces.show', $workspace);
    }

    /**
     * Workspace Owner or Administrator removes a Member. The Workspace
     * Owner can't be removed this way — per ADR-0009 (and ADR-0006's "can't
     * leave without transferring ownership first"), that requires
     * transferring ownership, which isn't part of this issue.
     */
    public function destroy(Request $request, Workspace $workspace, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        if ($workspace->isOwnedBy($user)) {
            abort(403, "The Workspace Owner can't be removed as a Member without first transferring ownership.");
        }

        $workspace->memberships()->where('user_id', $user->id)->delete();

        return redirect()->route('workspaces.show', $workspace);
    }
}
