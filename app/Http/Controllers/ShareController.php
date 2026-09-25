<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\Share;
use App\Repositories\VersionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ShareController extends Controller
{
    public function index(string $slug, VersionRepository $versions): JsonResponse
    {
        $diagram = Diagram::where('slug', $slug)->firstOrFail();

        $this->authorize('manageShares', $diagram);

        $shares = $diagram->shares()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'shares' => $shares->map(fn (Share $s) => $this->present($s, $diagram, $versions)),
        ]);
    }

    public function store(Request $request, string $slug, VersionRepository $versions): JsonResponse
    {
        $diagram = Diagram::where('slug', $slug)->firstOrFail();

        $this->authorize('manageShares', $diagram);

        $data = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'allow_download' => ['sometimes', 'boolean'],
            'pinned_version_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $days = $data['days'] ?? 7;
        $pinnedVersionId = $data['pinned_version_id'] ?? null;

        if ($pinnedVersionId !== null) {
            try {
                $versions->get($diagram, $pinnedVersionId);
            } catch (RuntimeException) {
                throw ValidationException::withMessages([
                    'pinned_version_id' => 'The selected version does not exist.',
                ]);
            }
        }

        $share = Share::create([
            'diagram_id' => $diagram->id,
            'expires_at' => now()->addDays($days),
            'allow_download' => $data['allow_download'] ?? false,
            'created_by' => $request->user()->id,
            'pinned_version_id' => $pinnedVersionId,
        ]);

        return response()->json($this->present($share, $diagram, $versions), 201);
    }

    public function destroy(Share $share): JsonResponse
    {
        $this->authorize('manageShares', $share->diagram);

        $share->update(['revoked_at' => now()]);

        return response()->json(['revoked' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Share $share, Diagram $diagram, VersionRepository $versions): array
    {
        $data = [
            'id' => $share->id,
            'url' => $share->showUrl(),
            'expires_at' => $share->expires_at->toIso8601String(),
            'allow_download' => $share->allow_download,
            'pinned_version' => $this->presentPinnedVersion($share, $diagram, $versions),
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
    private function presentPinnedVersion(Share $share, Diagram $diagram, VersionRepository $versions): ?array
    {
        if (! $share->isPinned()) {
            return null;
        }

        $version = collect($versions->list($diagram))->firstWhere('id', $share->pinned_version_id);

        return [
            'id' => $share->pinned_version_id,
            'number' => $version['number'] ?? null,
            'name' => $version['name'] ?? null,
        ];
    }
}
