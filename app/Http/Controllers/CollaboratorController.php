<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTargetUser;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\CollaboratorRepository;
use App\Repositories\DiagramListCriteria;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Services\CollaboratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
     * Diagram Owner, Diagram Manager or Administrator lists a Diagram's Collaborators/requests.
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
            'role' => $collaborator->role,
        ]);

        return response()->json($collaborators);
    }

    /**
     * Direct invite by the Diagram Owner/Diagram Manager/Administrator
     * (immediate access) or request by the User themselves (stays pending
     * until approved) — which of the two is CollaboratorPolicy's call. An
     * optional "role" (Diagram Owner/Administrator only) invites a Diagram
     * Manager.
     *
     * When targeting by email, returns a generic success response regardless
     * of whether the email matches an existing User, to avoid email enumeration.
     * When targeting by user_id, returns the usual detailed response.
     */
    public function store(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $data = $request->validate($this->userIdentifierRules() + $this->roleRules());

        $target = $this->resolveUserFrom($data);

        // Authorized whether or not the email matched anybody, so a refusal
        // never tells the caller if the email belongs to a User.
        $this->authorize('collaborator.invite', [$diagram, $target]);
        $this->authorizeRole($diagram, $data['role'] ?? null);

        $collaborator = $target
            ? $this->service->invite($diagram, $target, Gate::allows('collaborator.grant', $diagram), $data['role'] ?? null)
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
        return redirect()->route('diagrams.index', DiagramListCriteria::unifiedSearchQuery($request))
            ->with('status', __('messages.access_request_sent'));
    }

    /**
     * Diagram Owner, Diagram Manager or Administrator approves a pending
     * request; with a "role", the Diagram Owner/Administrator also sets it.
     */
    public function update(Request $request, string $slug, User $user): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $data = $request->validate($this->roleRules());

        $this->authorize('collaborator.grant', $diagram);
        $this->authorizeRole($diagram, $data['role'] ?? null);

        $collaborator = $this->service->approve($diagram, $user, $data['role'] ?? null);

        return response()->json([
            'user_id' => $collaborator->user_id,
            'status' => $collaborator->status,
            'role' => $collaborator->role,
        ]);
    }

    /**
     * Declines a pending request or removes an approved Collaborator (a
     * Diagram Manager only removes plain ones, or themselves).
     */
    public function destroy(string $slug, User $user): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('collaborator.revoke', [$diagram, $user]);

        $this->service->revoke($diagram, $user);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function roleRules(): array
    {
        return ['role' => ['sometimes', Rule::in([Collaborator::ROLE_COLLABORATOR, Collaborator::ROLE_MANAGER])]];
    }

    /**
     * Choosing a role is the Diagram Owner's/Administrator's call alone
     * (ADR-0017), whichever way the request came in.
     */
    private function authorizeRole(Diagram $diagram, ?string $role): void
    {
        if ($role !== null) {
            $this->authorize('collaborator.assignRole', $diagram);
        }
    }
}
