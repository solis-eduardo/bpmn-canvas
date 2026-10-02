<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTargetUser;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\UserRepository;
use App\Services\WorkspaceOwnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceOwnerController extends Controller
{
    use ResolvesTargetUser;

    public function __construct(
        private readonly UserRepository $users,
        private readonly WorkspaceOwnerService $owners,
    ) {}

    /**
     * Transfers the Workspace Owner role (CONTEXT.md, "Workspace Owner";
     * ADR-0006; issue #29). Redirects the same way whether or not the email
     * matches a User, to avoid email enumeration.
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('workspace-owner.transfer', $workspace);

        $data = $request->validate($this->userIdentifierRules());

        $this->actOnTargetUser($data, fn (User $owner) => $this->owners->transfer($workspace, $owner));

        return redirect()->route('workspaces.index');
    }
}
