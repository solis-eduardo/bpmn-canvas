<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\DiagramOldSlug;
use App\Repositories\VersionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class VersionController extends Controller
{
    public function versions(string $slug, VersionRepository $versions): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

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
        [$diagram] = $this->resolve($slug);

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
        [$diagram] = $this->resolve($slug);

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
        [$diagram] = $this->resolve($slug);

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

    /**
     * @return array{0: Diagram, 1: bool}
     */
    private function resolve(string $slug): array
    {
        $diagram = Diagram::where('slug', $slug)->first();

        if ($diagram) {
            return [$diagram, false];
        }

        $old = DiagramOldSlug::with('diagram')->where('old_slug', $slug)->first();

        if ($old && $old->diagram) {
            return [$old->diagram, true];
        }

        abort(404);
    }
}
