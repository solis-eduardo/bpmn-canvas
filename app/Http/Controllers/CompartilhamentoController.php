<?php

namespace App\Http\Controllers;

use App\Models\Compartilhamento;
use App\Models\Diagrama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompartilhamentoController extends Controller
{
    public function index(string $slug): JsonResponse
    {
        $diagrama = Diagrama::where('slug', $slug)->firstOrFail();

        $this->authorize('manageCompartilhamentos', $diagrama);

        $compartilhamentos = $diagrama->compartilhamentos()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'compartilhamentos' => $compartilhamentos->map(fn (Compartilhamento $c) => $this->apresentar($c)),
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $diagrama = Diagrama::where('slug', $slug)->firstOrFail();

        $this->authorize('manageCompartilhamentos', $diagrama);

        $data = $request->validate([
            'dias' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'allow_download' => ['sometimes', 'boolean'],
        ]);

        $dias = $data['dias'] ?? 7;

        $compartilhamento = Compartilhamento::create([
            'diagrama_id' => $diagrama->id,
            'expires_at' => now()->addDays($dias),
            'allow_download' => $data['allow_download'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($this->apresentar($compartilhamento), 201);
    }

    public function destroy(Compartilhamento $compartilhamento): JsonResponse
    {
        $this->authorize('manageCompartilhamentos', $compartilhamento->diagrama);

        $compartilhamento->update(['revoked_at' => now()]);

        return response()->json(['revoked' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function apresentar(Compartilhamento $compartilhamento): array
    {
        $data = [
            'id' => $compartilhamento->id,
            'url' => $compartilhamento->showUrl(),
            'expires_at' => $compartilhamento->expires_at->toIso8601String(),
            'allow_download' => $compartilhamento->allow_download,
        ];

        if ($downloadUrl = $compartilhamento->downloadUrl()) {
            $data['download_url'] = $downloadUrl;
        }

        return $data;
    }
}
