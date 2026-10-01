<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\DiagramWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
     * #27). Not being the Diagram Owner is a 403; being one but not a Member
     * of the target Workspace is a 422.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('putInWorkspace', $diagram);

        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
        ]);

        $workspace = $this->workspaces->findOrFail($data['workspace_id']);

        abort_unless(Gate::allows('diagram-workspace.assign', [$diagram, $workspace]), 422, __('messages.must_be_workspace_member'));

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
