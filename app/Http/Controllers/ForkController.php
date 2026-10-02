<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\ForkService;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function store(Request $request, string $slug): RedirectResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $data = $request->validate([
            'workspace_id' => ['nullable', 'integer'],
        ]);

        // An unknown Workspace is refused exactly like one the User isn't a
        // Member of, so the answer never tells whether the ID exists.
        $workspace = isset($data['workspace_id'])
            ? $this->workspaces->find($data['workspace_id']) ?? throw new AuthorizationException
            : null;

        $this->authorize('fork.create', [$diagram, $workspace]);

        $fork = $this->forks->create($diagram, $request->user(), $workspace);

        return redirect()->route('diagrams.show', $fork->slug);
    }
}
