<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
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
        private readonly VersionRepository $versions,
    ) {}

    /**
     * Forks the Diagram. A request may carry `content`, the edits open in the
     * Editor and not saved anywhere yet (the save-conflict dialog, issue
     * #243): the Fork then starts from those. Such a client asks for JSON and
     * is told where the Fork is, rather than redirected. It may instead name
     * a `version` of the Diagram to fork from (the history panel, issue #248),
     * and `copy_tags` asks for the origin's Tags to be copied.
     */
    public function store(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $data = $request->validate([
            'workspace_id' => ['nullable', 'integer'],
            'content' => ['bail', 'sometimes', 'string', 'max:'.BpmnXml::MAX_LENGTH, new BpmnXml],
            'version' => ['sometimes', 'string', 'max:64', 'prohibits:content'],
            'copy_tags' => ['sometimes', 'boolean'],
        ]);

        // An unknown Workspace is refused exactly like one the User isn't a
        // Member of, so the answer never tells whether the ID exists.
        $workspace = isset($data['workspace_id'])
            ? $this->workspaces->find($data['workspace_id']) ?? throw new AuthorizationException
            : null;

        $this->authorize('fork.create', [$diagram, $workspace]);

        $version = isset($data['version'])
            ? $this->versions->resolve($diagram, $data['version']) ?? abort(404)
            : null;

        $fork = $this->forks->create($diagram, $request->user(), $workspace, $data['content'] ?? null, (bool) ($data['copy_tags'] ?? false), $version);

        if ($request->expectsJson()) {
            return response()->json(['url' => route('diagrams.show', $fork->slug)], 201);
        }

        return redirect()->route('diagrams.show', $fork->slug);
    }
}
