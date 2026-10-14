<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDiagramWrites;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use App\Rules\BpmnXml;
use App\Services\SaveConflictDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A Diagram's BPMN content as a sub-resource: read it, or save a new Version.
 */
class DiagramContentController extends Controller
{
    use AuthorizesDiagramWrites;
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly VersionRepository $versions,
        private readonly SaveConflictDetector $conflicts,
    ) {}

    /**
     * The content, with the id of the Version it is at. The Editor sends that
     * id back as `base_version` on save (issue #243).
     */
    public function show(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('view', $diagram);

        // The Version is read before the content: a save landing in between
        // can then only make the base older than the content (a spurious
        // conflict), never newer (which would let a stale copy overwrite it).
        $version = $this->versions->currentVersionId($diagram);

        return response()->json([
            'slug' => $diagram->slug,
            'title' => $diagram->title,
            'content' => $this->diagrams->contentOf($diagram),
            'version' => $version,
        ]);
    }

    /**
     * Saves a new Version. When the request carries `base_version`, the save
     * proceeds only if it still names the current Version; otherwise someone
     * else saved in between, and the answer is a 409 naming the current one,
     * so nothing is overwritten silently. Without the key (older clients, API
     * use) the save is unconditional, as before.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorizeWrite($diagram);

        $user = $request->user();

        $data = $request->validate([
            'content' => ['required', 'string', new BpmnXml],
            'base_version' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        // Writing takes the Lock, so a save never lands outside one. Opening
        // the Editor normally acquires it first; this also covers a save
        // whose Lock expired (ADR-0003) while nobody else took it over.
        // The claim is conditional, so of two concurrent saves against a free
        // Lock only one proceeds — the other is told the Lock is gone rather
        // than silently overwriting it.
        $this->claimWriteLock($diagram, $user);

        // The Diagram row stays locked until the save is recorded, so two
        // saves checking the same base cannot both pass the check and write.
        $outcome = DB::transaction(function () use ($request, $diagram, $user, $data): array {
            DB::table('diagrams')->where('id', $diagram->id)->lockForUpdate()->first();

            // Without a base the save is unconditional. A newer Version only
            // counts as a conflict when it is not this User's own draft kept
            // by their expired Lock (SaveConflictDetector).
            $conflict = $request->has('base_version')
                ? $this->conflicts->conflictingVersion($diagram, $user, $data['base_version'] ?? null)
                : null;

            if ($conflict !== null) {
                return ['conflict' => $conflict];
            }

            // When the content repeats the last Version nothing new is
            // recorded, and the current one is still the answer.
            $saved = $this->versions->save($diagram, $user, $data['content']);

            return ['version' => $saved ?? $this->versions->currentVersionId($diagram)];
        });

        if (array_key_exists('conflict', $outcome)) {
            return response()->json([
                'message' => __('messages.error_version_conflict'),
                'current_version' => $outcome['conflict'],
            ], 409);
        }

        return response()->json(['saved' => true, 'version' => $outcome['version']]);
    }
}
