<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\WorkspaceRepository;
use App\Rules\BpmnXml;
use App\Services\ForkService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ForkController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly WorkspaceRepository $workspaces,
        private readonly ForkService $forks,
    ) {}

    /**
     * Forks the Diagram. A request may carry `content`, the edits open in the
     * Editor and not saved anywhere yet (the save-conflict dialog, issue
     * #243): the Fork then starts from those. Such a client asks for JSON and
     * is told where the Fork is, rather than redirected.
     */
    public function store(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $data = $request->validate([
            'workspace_id' => ['nullable', 'integer'],
            'content' => ['sometimes', 'string', new BpmnXml],
        ]);

        // An unknown Workspace is refused exactly like one the User isn't a
        // Member of, so the answer never tells whether the ID exists.
        $workspace = isset($data['workspace_id'])
            ? $this->workspaces->find($data['workspace_id']) ?? throw new AuthorizationException
            : null;

        $this->authorize('fork.create', [$diagram, $workspace]);

        $fork = $this->forks->create($diagram, $request->user(), $workspace, $data['content'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['url' => route('diagrams.show', $fork->slug)], 201);
        }

        return redirect()->route('diagrams.show', $fork->slug);
    }
}
