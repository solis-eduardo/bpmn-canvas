<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use App\Repositories\WorkspaceRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
    ) {}

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

        $data = $request->validate($this->userIdentifierRules());

        $member = $this->resolveUserFrom($data);

        // Only add the member if the User exists; silently do nothing if
        // the email doesn't match (avoiding email enumeration).
        if ($member && $this->workspaces->addMember($workspace, $member)->wasRecentlyCreated) {
            $member->notify(new WorkspaceMemberAdded($workspace));
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

        $this->removeAndNotify($workspace, $user);

        return redirect()->route('workspaces.index');
    }

    /**
     * The current User leaves a Workspace (removes themselves as a
     * Member). Self-service — no manageMembers authorization needed. The
     * Workspace Owner is refused: they must transfer ownership first
     * (CONTEXT.md, "Workspace Owner"; ADR-0006; issue #29), or the
     * Workspace would end up without one.
     */
    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        if ($workspace->isOwnedBy($user)) {
            abort(422, __('messages.workspace_owner_must_transfer_before_leaving'));
        }

        $this->removeAndNotify($workspace, $user);

        return redirect()->route('workspaces.index');
    }

    private function removeAndNotify(Workspace $workspace, User $user): void
    {
        if ($this->workspaces->removeMember($workspace, $user)) {
            $user->notify(new WorkspaceMemberRemoved($workspace));
        }
    }
}
