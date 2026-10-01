<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTargetUser;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Services\DiagramOwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagramOwnerController extends Controller
{
    use ResolvesTargetUser;
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly UserRepository $users,
        private readonly DiagramRepository $diagrams,
        private readonly DiagramOwnerService $owners,
    ) {}

    /**
     * Transfers the Diagram Owner role (CONTEXT.md, "Diagram Owner";
     * ADR-0006; issue #14).
     *
     * When targeting by email, answers the same generic success whether or
     * not the email matches a User, to avoid email enumeration — an HTTP
     * contract, not a domain rule, so it lives here. Targeting by user_id
     * answers in detail: validation already guarantees the ID exists.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('diagram-owner.transfer', $diagram);

        $data = $request->validate([
            ...$this->userIdentifierRules(),
            'give_up_access' => ['sometimes', 'boolean'],
        ]);

        $newOwner = $this->resolveUserFrom($data);

        if ($newOwner) {
            $this->owners->transfer($diagram, $newOwner, (bool) ($data['give_up_access'] ?? false));
        }

        return response()->json(isset($data['email'])
            ? ['success' => true]
            : ['owner_id' => $newOwner->id]);
    }
}
