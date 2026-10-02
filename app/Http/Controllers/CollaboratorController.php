<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTargetUser;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\CollaboratorRepository;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Services\CollaboratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CollaboratorController extends Controller
{
    use ResolvesTargetUser;
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly UserRepository $users,
        private readonly DiagramRepository $diagrams,
        private readonly CollaboratorRepository $collaborators,
        private readonly CollaboratorService $service,
    ) {}

    /**
     * Author (or Administrator) lists a Diagram's Collaborators/requests.
     */
    public function index(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageCollaborators', $diagram);

        $collaborators = $this->collaborators->listWithUser($diagram)->map(fn (Collaborator $collaborator) => [
            'user_id' => $collaborator->user_id,
            'name' => $collaborator->user->name,
            'email' => $collaborator->user->email,
            'status' => $collaborator->status,
        ]);

        return response()->json($collaborators);
    }

    /**
     * Direct invite by the Diagram Owner/Administrator (immediate access) or
     * request by the User themselves (stays pending until approved) — which
     * of the two is CollaboratorPolicy's call.
     *
     * When targeting by email, returns a generic success response regardless
     * of whether the email matches an existing User, to avoid email enumeration.
     * When targeting by user_id, returns the usual detailed response.
     */
    public function store(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $data = $request->validate($this->userIdentifierRules());

        $target = $this->resolveUserFrom($data);

        // Authorized whether or not the email matched anybody, so a refusal
        // never tells the caller if the email belongs to a User.
        $this->authorize('collaborator.invite', [$diagram, $target]);

        $collaborator = $target
            ? $this->service->invite($diagram, $target, Gate::allows('collaborator.grant', $diagram))
            : null;

        if ($this->targetedByEmail($data)) {
            return response()->json(['success' => true], 201);
        }

        if (! $request->expectsJson()) {
            return $this->requestRedirect($request);
        }

        return response()->json([
            'user_id' => $collaborator->user_id,
            'status' => $collaborator->status,
        ], 201);
    }

    private function requestRedirect(Request $request): RedirectResponse
    {
        $query = $request->query();
        $search = trim((string) ($query['q'] ?? $query['discover'] ?? ''));
        unset($query['discover'], $query['page'], $query['discovery_page']);
        $query = $search === '' ? $query : ['q' => $search] + $query;

        return redirect()->route('diagrams.index', $query)->with('status', __('messages.access_request_sent'));
    }

    /**
     * Diagram Owner or Administrator approves a pending request.
     */
    public function update(string $slug, User $user): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('collaborator.grant', $diagram);

        $collaborator = $this->service->approve($diagram, $user);

        return response()->json([
            'user_id' => $collaborator->user_id,
            'status' => $collaborator->status,
        ]);
    }

    /**
     * Declines a pending request or removes an approved Collaborator.
     */
    public function destroy(string $slug, User $user): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('collaborator.revoke', $diagram);

        $this->service->revoke($diagram, $user);

        return response()->json(['deleted' => true]);
    }
}
