<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\DiagramWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagramWorkspaceController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly WorkspaceRepository $workspaces,
        private readonly DiagramWorkspaceService $placement,
    ) {}

    /**
     * Puts the Diagram into a Workspace (CONTEXT.md, "Diagram Owner"; issue
     * #27).
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $workspace = $this->workspaces->findOrFail($data['workspace_id']);

        $this->authorize('diagram-workspace.assign', [$diagram, $workspace]);

        $this->placement->assign($diagram, $workspace);

        return response()->json(['workspace_id' => $workspace->id]);
    }

    public function destroy(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('diagram-workspace.remove', $diagram);

        $this->placement->remove($diagram);

        return response()->json(['workspace_id' => null]);
    }
}
