<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use App\Rules\BpmnXml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A Diagram's BPMN content as a sub-resource: read it, or save a new Version.
 */
class DiagramContentController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly VersionRepository $versions,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('view', $diagram);

        return response()->json([
            'slug' => $diagram->slug,
            'title' => $diagram->title,
            'content' => $this->diagrams->contentOf($diagram),
        ]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        abort_unless(Gate::allows('update', $diagram), 403, __('messages.error_cannot_edit_diagram'));

        $user = $request->user();

        if ($diagram->isLockedByOther($user)) {
            abort(423, __('messages.error_diagram_locked_by', ['name' => $diagram->lockedBy->name]));
        }

        $data = $request->validate([
            'content' => ['required', 'string', new BpmnXml],
        ]);

        $this->versions->save($diagram, $request->user(), $data['content']);

        return response()->json(['saved' => true]);
    }
}
