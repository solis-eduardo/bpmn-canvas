<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTargetUser;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\UserRepository;
use App\Services\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MemberController extends Controller
{
    use ResolvesTargetUser;

    public function __construct(
        private readonly UserRepository $users,
        private readonly MemberService $members,
    ) {}

    /**
     * Adds a Member (CONTEXT.md, "Member"): no invitation/acceptance flow,
     * unlike a Diagram's Collaborator. Redirects the same way whether or not
     * the email matches a User, to avoid email enumeration.
     */
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('member.add', $workspace);

        $data = $request->validate($this->userIdentifierRules());

        $member = $this->resolveUserFrom($data);

        if ($member) {
            $this->members->add($workspace, $member);
        }

        return redirect()->route('workspaces.index');
    }

    /**
     * Not managing the Workspace is a 403; managing it but targeting the
     * Workspace Owner is a 422 — the Workspace would end up ownerless.
     */
    public function destroy(Workspace $workspace, User $user): RedirectResponse
    {
        $this->authorize('member.add', $workspace);

        abort_unless(Gate::allows('member.remove', [$workspace, $user]), 422, __('messages.cannot_remove_workspace_owner'));

        $this->members->remove($workspace, $user);

        return redirect()->route('workspaces.index');
    }

    /**
     * Self-service. The Workspace Owner must transfer ownership first
     * (ADR-0006; issue #29).
     */
    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless(Gate::allows('member.leave', $workspace), 422, __('messages.workspace_owner_must_transfer_before_leaving'));

        $this->members->leave($workspace, $request->user());

        return redirect()->route('workspaces.index');
    }
}
