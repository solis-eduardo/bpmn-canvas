<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTargetUser;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\UserRepository;
use App\Services\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $this->rejectIfAlreadyMember($data, $workspace);

        $this->actOnTargetUser($data, fn (User $member) => $this->members->add($workspace, $member));

        return redirect()->route('workspaces.edit', $workspace);
    }

    /**
     * Replaces the Workspace's Members with the submitted set (the chips of
     * the Members picker, issue #170): new chips are added, removed chips are
     * removed, each with its notification. The Workspace Owner can't be
     * dropped, same as destroy().
     */
    public function sync(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('member.add', $workspace);

        $data = $request->validate([
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', $this->activeUserRule()],
        ]);

        $wanted = collect($data['members'] ?? [])->map(fn ($id) => (int) $id)->unique();

        if (! $wanted->contains($workspace->owner_id) && ! $workspace->owner->isDeactivated()) {
            throw ValidationException::withMessages(['members' => __('messages.cannot_remove_workspace_owner')]);
        }

        $current = $workspace->members()->pluck('user_id');

        $this->users->findMany($wanted->diff($current)->all())
            ->each(fn (User $user) => $this->members->add($workspace, $user));

        $this->users->findMany($current->diff($wanted)->all())
            ->each(fn (User $user) => $this->members->remove($workspace, $user));

        return redirect()->route('workspaces.edit', $workspace);
    }

    /**
     * A picked `user_id` can't probe anything, so a stale form is told
     * plainly; an email stays silent (see actOnTargetUser()).
     */
    private function rejectIfAlreadyMember(array $data, Workspace $workspace): void
    {
        if (isset($data['user_id']) && $workspace->members()->where('user_id', $data['user_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => __('messages.user_already_member')]);
        }
    }

    /**
     * Refused for the Workspace Owner: the Workspace would end up ownerless.
     */
    public function destroy(Workspace $workspace, User $user): RedirectResponse
    {
        $this->authorize('member.remove', [$workspace, $user]);

        $this->members->remove($workspace, $user);

        return redirect()->route('workspaces.edit', $workspace);
    }

    /**
     * Self-service. The Workspace Owner must transfer ownership first
     * (ADR-0006; issue #29).
     */
    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('member.leave', $workspace);

        $this->members->leave($workspace, $request->user());

        return redirect()->route('workspaces.index');
    }
}
