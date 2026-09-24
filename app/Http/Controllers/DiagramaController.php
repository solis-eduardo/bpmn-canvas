<?php

namespace App\Http\Controllers;

use App\Models\Diagrama;
use App\Models\DiagramaSlugAntigo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DiagramaController extends Controller
{
    public function index(): View
    {
        $diagramas = Diagrama::with(['author', 'lockedBy'])->orderBy('title')->get();

        return view('diagramas.index', compact('diagramas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $slug = Diagrama::uniqueSlug($data['title']);
        $diskPath = $slug.'/diagram.bpmn';

        $diagrama = Diagrama::create([
            'title' => $data['title'],
            'slug' => $slug,
            'author_id' => $request->user()->id,
            'disk_path' => $diskPath,
        ]);

        try {
            Storage::disk('diagrams')->put($diskPath, Diagrama::EMPTY_BPMN);
        } catch (\Throwable $e) {
            $diagrama->delete();

            throw $e;
        }

        return redirect()->route('diagramas.show', $diagrama->slug);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        [$diagrama, $isOldSlug] = $this->resolve($slug);

        if ($isOldSlug) {
            return redirect()->route('diagramas.show', $diagrama->slug);
        }

        $this->authorize('view', $diagrama);

        $user = $request->user();

        if ($diagrama->isLockedByOther($user)) {
            return view('diagramas.locked', [
                'diagrama' => $diagrama,
                'lockedBy' => $diagrama->lockedBy,
            ]);
        }

        if (! $diagrama->heartbeat($user)) {
            $diagrama->acquireLock($user);
        }

        return view('diagramas.show', compact('diagrama'));
    }

    public function content(string $slug): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $this->authorize('view', $diagrama);

        return response()->json([
            'slug' => $diagrama->slug,
            'title' => $diagrama->title,
            'content' => Storage::disk('diagrams')->get($diagrama->disk_path),
        ]);
    }

    public function save(Request $request, string $slug): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $this->authorize('update', $diagrama);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        Storage::disk('diagrams')->put($diagrama->disk_path, $data['content']);

        return response()->json(['saved' => true]);
    }

    public function rename(Request $request, string $slug): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $newSlug = Diagrama::uniqueSlug($data['title'], $diagrama->id);
        $oldSlug = $diagrama->slug;
        $filename = basename($diagrama->disk_path);
        $slugChanged = $newSlug !== $oldSlug;

        if ($slugChanged) {
            Storage::disk('diagrams')->move($oldSlug, $newSlug);
        }

        try {
            DB::transaction(function () use ($diagrama, $oldSlug, $newSlug, $filename, $data, $slugChanged) {
                if ($slugChanged) {
                    DiagramaSlugAntigo::create([
                        'diagrama_id' => $diagrama->id,
                        'slug_antigo' => $oldSlug,
                    ]);
                }

                $diagrama->update([
                    'title' => $data['title'],
                    'slug' => $newSlug,
                    'disk_path' => $newSlug.'/'.$filename,
                ]);
            });
        } catch (\Throwable $e) {
            if ($slugChanged) {
                Storage::disk('diagrams')->move($newSlug, $oldSlug);
            }

            throw $e;
        }

        return response()->json([
            'slug' => $diagrama->slug,
            'title' => $diagrama->title,
        ]);
    }

    public function heartbeat(Request $request, string $slug): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        if (! $diagrama->heartbeat($request->user())) {
            return response()->json(['message' => 'Você não detém a trava deste diagrama.'], 403);
        }

        return response()->json(['heartbeat' => true]);
    }

    public function unlock(Request $request, string $slug): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $diagrama->releaseLock($request->user());

        return response()->json(['unlocked' => true]);
    }

    public function fork(Request $request, string $slug): RedirectResponse
    {
        [$diagrama] = $this->resolve($slug);

        $newSlug = Diagrama::uniqueSlug($diagrama->title);
        $diskPath = $newSlug.'/diagram.bpmn';

        $forked = Diagrama::create([
            'title' => $diagrama->title,
            'slug' => $newSlug,
            'author_id' => $request->user()->id,
            'disk_path' => $diskPath,
            'forked_from_id' => $diagrama->id,
        ]);

        try {
            Storage::disk('diagrams')->put($diskPath, Storage::disk('diagrams')->get($diagrama->disk_path));
        } catch (\Throwable $e) {
            $forked->delete();

            throw $e;
        }

        return redirect()->route('diagramas.show', $forked->slug);
    }

    public function destroy(string $slug): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $slug = $diagrama->slug;

        $diagrama->delete();

        Storage::disk('diagrams')->deleteDirectory($slug);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{0: Diagrama, 1: bool}
     */
    private function resolve(string $slug): array
    {
        $diagrama = Diagrama::where('slug', $slug)->first();

        if ($diagrama) {
            return [$diagrama, false];
        }

        $antigo = DiagramaSlugAntigo::with('diagrama')->where('slug_antigo', $slug)->first();

        if ($antigo && $antigo->diagrama) {
            return [$antigo->diagrama, true];
        }

        abort(404);
    }
}
