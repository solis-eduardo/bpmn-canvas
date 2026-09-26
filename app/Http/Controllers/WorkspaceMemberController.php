<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    /**
     * Adds a Member to a Workspace: Workspace Owner or Administrator only
     * (CONTEXT.md, "Member"). "Members are added by the Workspace Owner" —
     * no invitation/acceptance flow, unlike a Diagram's Collaborator.
     *
     * Returns silently regardless of whether the email matches an existing User
     * (avoiding email enumeration).
     */
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $data = $request->validate([
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email'],
        ]);

        $member = $this->resolveUserFrom($data);

        // Only add the member if the User exists; silently do nothing if
        // the email doesn't match (avoiding email enumeration).
        if ($member) {
            $workspace->addMember($member);
        }

        return redirect()->route('workspaces.index');
    }

    /**
     * Removes a Member from a Workspace: Workspace Owner or Administrator
     * only. Refuses to remove the Workspace Owner themselves — there's no
     * ownership-transfer flow yet (later ticket, ADR-0006), so removing the
     * Owner would leave the Workspace ownerless.
     */
    public function destroy(Workspace $workspace, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        if ($workspace->isOwnedBy($user)) {
            abort(422, __('messages.cannot_remove_workspace_owner'));
        }

        $workspace->removeMember($user);

        return redirect()->route('workspaces.index');
    }
}
