<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\ForkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

        $this->authorize('fork.create', $diagram);

        $data = $request->validate([
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
        ]);

        $workspace = isset($data['workspace_id'])
            ? $this->workspaces->findOrFail($data['workspace_id'])
            : null;

        abort_unless(Gate::allows('fork.create', [$diagram, $workspace]), 422, __('messages.must_be_workspace_member'));

        $fork = $this->forks->create($diagram, $request->user(), $workspace);

        return redirect()->route('diagrams.show', $fork->slug);
    }
}
