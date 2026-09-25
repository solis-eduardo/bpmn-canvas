<?php

namespace App\Http\Controllers;

use App\Actions\Diagrams\ReleaseLock;
use App\Actions\Diagrams\RequestLock;
use App\Models\Diagram;
use App\Models\DiagramOldSlug;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class DiagramController extends Controller
{
    public function index(): View
    {
        $diagrams = Diagram::with(['author', 'owner', 'lockedBy'])->orderBy('title')->get();

        return view('diagrams.index', compact('diagrams'));
    }

    public function store(Request $request, DiagramRepository $diagrams, VersionRepository $versions): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $slug = Diagram::uniqueSlug($data['title']);

        $diagram = $diagrams->create([
            'title' => $data['title'],
            'slug' => $slug,
            'author_id' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'disk_path' => $slug.'/diagram.bpmn',
        ], Diagram::emptyBpmn());

        $versions->initialize($diagram, $request->user());

        return redirect()->route('diagrams.show', $diagram->slug);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        [$diagram, $isOldSlug] = $this->resolve($slug);

        if ($isOldSlug) {
            return redirect()->route('diagrams.show', $diagram->slug);
        }

        $this->authorize('view', $diagram);

        $user = $request->user();

        if ($diagram->isLockedByOther($user)) {
            return view('diagrams.locked', [
                'diagram' => $diagram,
                'lockedBy' => $diagram->lockedBy,
            ]);
        }

        if (! $diagram->heartbeat($user)) {
            $diagram->acquireLock($user);
        }

        $diagram->loadMissing(['author', 'owner']);

        return view('diagrams.show', compact('diagram'));
    }

    public function content(string $slug): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        $this->authorize('view', $diagram);

        return response()->json([
            'slug' => $diagram->slug,
            'title' => $diagram->title,
            'content' => Storage::disk('diagrams')->get($diagram->disk_path),
        ]);
    }

    public function save(Request $request, string $slug, VersionRepository $versions): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        $this->authorize('update', $diagram);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $versions->save($diagram, $request->user(), $data['content']);

        return response()->json(['saved' => true]);
    }

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
        [$diagram] = $this->resolve($slug);

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
            $olderContent = $versions->get($diagram, $olderMeta['id']);
            $newerContent = $versions->get($diagram, $newerMeta['id']);
        } catch (RuntimeException $e) {
            abort(404);
        }

        return response()->json([
            'older' => [
                'id' => $olderMeta['id'],
                'number' => $olderMeta['number'],
                'name' => $olderMeta['name'],
                'content' => $olderContent,
            ],
            'newer' => [
                'id' => $newerMeta['id'],
                'number' => $newerMeta['number'],
                'name' => $newerMeta['name'],
                'content' => $newerContent,
            ],
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

    public function rename(Request $request, string $slug, DiagramRepository $diagrams): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $newSlug = Diagram::uniqueSlug($data['title'], $diagram->id);

        $diagram = $diagrams->move($diagram, $data['title'], $newSlug);

        return response()->json([
            'slug' => $diagram->slug,
            'title' => $diagram->title,
        ]);
    }

    public function heartbeat(Request $request, string $slug): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        if (! $diagram->heartbeat($request->user())) {
            return response()->json(['message' => __('messages.lock_not_held')], 403);
        }

        return response()->json(['heartbeat' => true]);
    }

    public function unlock(Request $request, string $slug, ReleaseLock $releaseLock): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        if (! $releaseLock($diagram, $request->user())) {
            return response()->json(['message' => __('messages.lock_not_held')], 403);
        }

        return response()->json(['unlocked' => true]);
    }

    /**
     * Requests the Lock on a Diagram locked by another User. Returns
     * "acquired" if the Lock was obtained (directly, or via automatic
     * transfer after LOCK_REQUEST_TRANSFER_SECONDS without a response from
     * the holder) or "waiting" while the deadline hasn't passed — the
     * client should poll again until it gets "acquired".
     */
    public function requestLock(Request $request, string $slug, RequestLock $requestLock): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        $this->authorize('view', $diagram);

        return response()->json($requestLock($diagram, $request->user()));
    }

    public function fork(Request $request, string $slug, DiagramRepository $diagrams, VersionRepository $versions): RedirectResponse
    {
        [$diagram] = $this->resolve($slug);

        $this->authorize('view', $diagram);

        // The "diagrams" disk is configured with `throw => false`, so a
        // failed read comes back as null rather than an exception. Catch
        // that explicitly: silently coercing null/false into an empty
        // string would register a Fork with corrupted (empty) content
        // instead of registering nothing.
        $content = Storage::disk('diagrams')->get($diagram->disk_path);

        if (! is_string($content)) {
            throw new RuntimeException("Could not read the Diagram's content to fork it: {$diagram->disk_path}");
        }

        $newSlug = Diagram::uniqueSlug($diagram->title);

        $forked = $diagrams->create([
            'title' => $diagram->title,
            'slug' => $newSlug,
            'author_id' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'disk_path' => $newSlug.'/diagram.bpmn',
            'forked_from_id' => $diagram->id,
        ], $content);

        // A Fork has its own, independent Version history, starting again at
        // Version 1 (CONTEXT.md, "Version") — its own Git repository, never
        // sharing history with the origin Diagram's.
        $versions->initialize($forked, $request->user());

        return redirect()->route('diagrams.show', $forked->slug);
    }

    public function destroy(string $slug, DiagramRepository $diagrams): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        $this->authorize('delete', $diagram);

        $diagrams->remove($diagram);

        return response()->json(['deleted' => true]);
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
