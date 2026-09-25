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
        ], Diagram::EMPTY_BPMN);

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
                ])->values(),
            ])
            ->values();

        return response()->json(['versions' => $grouped]);
    }

    public function restoreVersion(Request $request, string $slug, string $version, VersionRepository $versions): JsonResponse
    {
        [$diagram] = $this->resolve($slug);

        $this->authorize('update', $diagram);

        try {
            $versions->restore($diagram, $version, $request->user());
        } catch (\RuntimeException $e) {
            abort(404);
        }

        return response()->json(['restored' => true]);
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

    public function fork(Request $request, string $slug, DiagramRepository $diagrams): RedirectResponse
    {
        [$diagram] = $this->resolve($slug);

        $this->authorize('view', $diagram);

        $newSlug = Diagram::uniqueSlug($diagram->title);

        $forked = $diagrams->create([
            'title' => $diagram->title,
            'slug' => $newSlug,
            'author_id' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'disk_path' => $newSlug.'/diagram.bpmn',
            'forked_from_id' => $diagram->id,
        ], Storage::disk('diagrams')->get($diagram->disk_path));

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
