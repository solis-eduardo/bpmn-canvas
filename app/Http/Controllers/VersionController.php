<?php

namespace App\Http\Controllers;

use App\Exceptions\LockUnavailableException;
use App\Http\Controllers\Concerns\AuthorizesDiagramWrites;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class VersionController extends Controller
{
    use AuthorizesDiagramWrites;
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
    ) {}

    public function versions(string $slug, VersionRepository $versions): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('view', $diagram);

        $history = collect($versions->list($diagram));
        // The history is newest first, so the first Version is the current
        // one, even when it was deleted from the list below.
        $currentId = $history->first()['id'] ?? null;

        // Flat and newest first: grouping by day is a display concern of the
        // Editor, done in the User's timezone (ADR-0019).
        $items = $history
            ->reject(fn (array $version) => $version['hidden'])
            ->map(fn (array $version) => [
                'id' => $version['id'],
                'is_current' => $version['id'] === $currentId,
                'saved_at' => $version['saved_at']->toIso8601String(),
                'author' => $version['author'],
                'number' => $version['number'],
                'name' => $version['name'],
                'renamed' => $version['renamed'],
                'provisional' => $version['provisional'],
            ])
            ->values();

        return response()->json(['versions' => $items]);
    }

    /**
     * BPMN content of a single Version, used to compare it against another
     * source (e.g. the current content) on the canvas. Deliberately generic
     * — it doesn't assume "old vs current": it just returns one Version's
     * content, so it works for comparing any two Versions too.
     */
    public function versionContent(string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('update', $diagram);

        try {
            $content = $versions->get($diagram, $version);
        } catch (RuntimeException $e) {
            abort(404);
        }

        return response()->json([
            'id' => $version,
            'content' => $content,
        ]);
    }

    public function restoreVersion(Request $request, string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        // Restoring rewrites the content, so it goes through the same gate as
        // saving: nobody else may hold the Lock, and a free one is taken first
        // (see DiagramContentController::update()). Otherwise the holder's
        // draft is dropped by the restore and their next save overwrites it.
        $this->authorizeWrite($diagram);

        $user = $request->user();

        if (! $diagram->isLockedBy($user) && ! $diagram->claimFreeLock($user)) {
            throw LockUnavailableException::lost();
        }

        try {
            $versions->restore($diagram, $version, $user);
        } catch (RuntimeException $e) {
            abort(404);
        }

        return response()->json(['restored' => true]);
    }

    /**
     * Gives a Version a custom name to mark a milestone. Anyone who can edit
     * the Diagram can rename a Version — same permission as saving/restoring
     * (CONTEXT.md, "Version").
     */
    public function renameVersion(Request $request, string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('update', $diagram);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $versions->rename($diagram, $version, $data['name']);
        } catch (RuntimeException $e) {
            abort(404);
        }

        $version = $versions->resolve($diagram, $version) ?? $version;
        $renamed = collect($versions->list($diagram))->firstWhere('id', $version);

        return response()->json([
            'id' => $version,
            'number' => $renamed['number'] ?? null,
            'name' => $data['name'],
            'renamed' => true,
        ]);
    }

    /**
     * Keeps a provisional Version: it stops being provisional and stays in
     * the history as an ordinary Version. Anyone who can edit the Diagram
     * may do it, same as renaming.
     */
    public function promoteVersion(string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        return $this->resolveProvisional($slug, $version, $versions->makePermanent(...));
    }

    /**
     * Deletes a provisional Version from the history (soft delete, see
     * VersionRepository::hide()). Only provisional Versions can be deleted.
     */
    public function deleteVersion(string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        return $this->resolveProvisional($slug, $version, $versions->hide(...));
    }

    /**
     * @param  callable(Diagram, string): bool  $change
     */
    private function resolveProvisional(string $slug, string $version, callable $change): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('update', $diagram);

        try {
            $changed = $change($diagram, $version);
        } catch (RuntimeException $e) {
            abort(404);
        }

        abort_unless($changed, 409);

        return response()->json(['id' => $version]);
    }
}
