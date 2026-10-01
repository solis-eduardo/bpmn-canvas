<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class VersionController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
    ) {}

    public function versions(string $slug, VersionRepository $versions): JsonResponse
    {
        [$diagram] = $this->resolveDiagramOrOldSlug($slug);

        $this->authorize('view', $diagram);

        $grouped = collect($versions->list($diagram))
            ->groupBy(fn (array $version) => $version['saved_at']->toDateString())
            ->map(fn ($items, string $day) => [
                'day' => $day,
                'items' => $items->map(fn (array $version) => [
                    'id' => $version['id'],
                    'saved_at' => $version['saved_at']->toIso8601String(),
                    'author' => $version['author'],
                    'number' => $version['number'],
                    'name' => $version['name'],
                    'renamed' => $version['renamed'],
                ])->values(),
            ])
            ->values();

        return response()->json(['versions' => $grouped]);
    }

    /**
     * BPMN content of a single Version, used to compare it against another
     * source (e.g. the current content) on the canvas. Deliberately generic
     * — it doesn't assume "old vs current": it just returns one Version's
     * content, so it works for comparing any two Versions too.
     */
    public function versionContent(string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        [$diagram] = $this->resolveDiagramOrOldSlug($slug);

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

    /**
     * BPMN content of any two Versions, resolved in one request (ticket 36 —
     * extends the "old vs current" comparison from ticket 35 to any pair of
     * Versions). Reuses VersionRepository::list()/get() exactly like
     * versionContent() above; the only new behavior is figuring out which of
     * the two is chronologically older by Version number, so the canvas
     * always diffs "older -> newer" no matter which order the User picked
     * them in.
     */
    public function compareVersions(Request $request, string $slug, VersionRepository $versions): JsonResponse
    {
        [$diagram] = $this->resolveDiagramOrOldSlug($slug);

        $this->authorize('update', $diagram);

        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);

        $history = collect($versions->list($diagram))->keyBy('id');

        $fromMeta = $history->get($data['from']);
        $toMeta = $history->get($data['to']);

        if (! $fromMeta || ! $toMeta) {
            abort(404);
        }

        [$olderMeta, $newerMeta] = $fromMeta['number'] <= $toMeta['number']
            ? [$fromMeta, $toMeta]
            : [$toMeta, $fromMeta];

        try {
            $content = $versions->getMany($diagram, [$olderMeta['id'], $newerMeta['id']]);
        } catch (RuntimeException $e) {
            abort(404);
        }

        $describe = fn (array $meta): array => [
            'id' => $meta['id'],
            'number' => $meta['number'],
            'name' => $meta['name'],
            'content' => $content[$meta['id']],
        ];

        return response()->json([
            'older' => $describe($olderMeta),
            'newer' => $describe($newerMeta),
        ]);
    }

    public function restoreVersion(Request $request, string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        [$diagram] = $this->resolveDiagramOrOldSlug($slug);

        $this->authorize('update', $diagram);

        try {
            $versions->restore($diagram, $version, $request->user());
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
        [$diagram] = $this->resolveDiagramOrOldSlug($slug);

        $this->authorize('update', $diagram);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $versions->rename($diagram, $version, $data['name']);
        } catch (RuntimeException $e) {
            abort(404);
        }

        $renamed = collect($versions->list($diagram))->firstWhere('id', $version);

        return response()->json([
            'id' => $version,
            'number' => $renamed['number'] ?? null,
            'name' => $data['name'],
            'renamed' => true,
        ]);
    }
}
