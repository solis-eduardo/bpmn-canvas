<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Models\Share;
use App\Repositories\DiagramRepository;
use App\Repositories\ShareRepository;
use App\Repositories\VersionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ShareController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly ShareRepository $shares,
    ) {}

    public function index(string $slug, VersionRepository $versions): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('manageShares', $diagram);

        $shares = $this->shares->activeFor($diagram);

        $versionsById = $this->versionsById($diagram, $versions, $shares->contains(fn (Share $s) => $s->isPinned()));

        return response()->json([
            'shares' => $shares->map(fn (Share $s) => $this->present($s, $versionsById)),
        ]);
    }

    public function store(Request $request, string $slug, VersionRepository $versions): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('manageShares', $diagram);

        $data = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'allow_download' => ['sometimes', 'boolean'],
            'pinned_version_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $days = $data['days'] ?? 7;
        $pinnedVersionId = $data['pinned_version_id'] ?? null;

        $versionsById = $this->versionsById($diagram, $versions, $pinnedVersionId !== null);

        if ($pinnedVersionId !== null && ! $versionsById->has($pinnedVersionId)) {
            throw ValidationException::withMessages([
                'pinned_version_id' => 'The selected version does not exist.',
            ]);
        }

        $share = $this->shares->create([
            'diagram_id' => $diagram->id,
            'expires_at' => now()->addDays($days),
            'allow_download' => $data['allow_download'] ?? false,
            'created_by' => $request->user()->id,
            'pinned_version_id' => $pinnedVersionId,
        ]);

        return response()->json($this->present($share, $versionsById), 201);
    }

    public function destroy(Share $share): JsonResponse
    {
        $this->authorize('manageShares', $share->diagram);

        $this->shares->revoke($share);

        return response()->json(['revoked' => true]);
    }

    /**
     * The Diagram's Version history, keyed by Version id, fetched at most
     * once per request regardless of how many Shares need it — avoids a
     * `git log` shell-out (via VersionRepository::list()) per Share when
     * presenting a list, since every pinned Share on the same Diagram would
     * otherwise trigger its own, identical lookup.
     */
    private function versionsById(Diagram $diagram, VersionRepository $versions, bool $needed): Collection
    {
        return $needed ? collect($versions->list($diagram))->keyBy('id') : collect();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Share $share, Collection $versionsById): array
    {
        $data = [
            'id' => $share->id,
            'url' => $share->showUrl(),
            'expires_at' => $share->expires_at->toIso8601String(),
            'allow_download' => $share->allow_download,
            'pinned_version' => $this->presentPinnedVersion($share, $versionsById),
        ];

        if ($downloadUrl = $share->downloadUrl()) {
            $data['download_url'] = $downloadUrl;
        }

        return $data;
    }

    /**
     * The Version a Share is pinned to, described the same way the Version
     * history already describes a Version (number/name) rather than
     * exposing the underlying identifier's format — null means the Share
     * follows the latest Version (see CONTEXT.md, "Share").
     *
     * @return array<string, mixed>|null
     */
    private function presentPinnedVersion(Share $share, Collection $versionsById): ?array
    {
        if (! $share->isPinned()) {
            return null;
        }

        // Guaranteed present: a Share can only be pinned to a Version id
        // that store() already validated against this same Diagram's
        // history, and Version history is append-only.
        $version = $versionsById->get($share->pinned_version_id);

        return [
            'id' => $share->pinned_version_id,
            'number' => $version['number'],
            'name' => $version['name'],
        ];
    }
}
