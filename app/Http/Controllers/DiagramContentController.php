<?php

namespace App\Http\Controllers;

use App\Exceptions\LockUnavailableException;
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

        if (! Gate::allows('lock.write', $diagram)) {
            throw LockUnavailableException::heldByOther($diagram);
        }

        $data = $request->validate([
            'content' => ['required', 'string', new BpmnXml],
        ]);

        // Writing takes the Lock, so a save never lands outside one. Opening
        // the Editor normally acquires it first; this also covers a save
        // whose Lock expired (ADR-0003) while nobody else took it over.
        // The claim is conditional, so of two concurrent saves against a free
        // Lock only one proceeds — the other is told the Lock is gone rather
        // than silently overwriting it.
        if (! $diagram->isLockedBy($user) && ! $diagram->claimFreeLock($user)) {
            throw LockUnavailableException::lost();
        }

        $this->versions->save($diagram, $user, $data['content']);

        return response()->json(['saved' => true]);
    }
}
