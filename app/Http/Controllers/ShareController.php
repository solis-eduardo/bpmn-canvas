<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    public function index(string $slug): JsonResponse
    {
        $diagram = Diagram::where('slug', $slug)->firstOrFail();

        $this->authorize('manageShares', $diagram);

        $shares = $diagram->shares()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'shares' => $shares->map(fn (Share $s) => $this->present($s)),
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $diagram = Diagram::where('slug', $slug)->firstOrFail();

        $this->authorize('manageShares', $diagram);

        $data = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'allow_download' => ['sometimes', 'boolean'],
        ]);

        $days = $data['days'] ?? 7;

        $share = Share::create([
            'diagram_id' => $diagram->id,
            'expires_at' => now()->addDays($days),
            'allow_download' => $data['allow_download'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($this->present($share), 201);
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
    private function present(Share $share): array
    {
        $data = [
            'id' => $share->id,
            'url' => $share->showUrl(),
            'expires_at' => $share->expires_at->toIso8601String(),
            'allow_download' => $share->allow_download,
        ];

        if ($downloadUrl = $share->downloadUrl()) {
            $data['download_url'] = $downloadUrl;
        }

        return $data;
    }
}
