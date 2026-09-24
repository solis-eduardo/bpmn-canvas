<?php

namespace App\Http\Controllers;

use App\Models\Compartilhamento;
use App\Models\Diagrama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class CompartilhamentoController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $diagrama = Diagrama::where('slug', $slug)->firstOrFail();

        $this->autorizarAutor($request, $diagrama);

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

        $this->autorizarAutor($request, $diagrama);

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

    public function destroy(Request $request, Compartilhamento $compartilhamento): JsonResponse
    {
        $this->autorizarAutor($request, $compartilhamento->diagrama);

        $compartilhamento->update(['revoked_at' => now()]);

        return response()->json(['revoked' => true]);
    }

    private function autorizarAutor(Request $request, Diagrama $diagrama): void
    {
        abort_unless($diagrama->author_id === $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function apresentar(Compartilhamento $compartilhamento): array
    {
        $data = [
            'id' => $compartilhamento->id,
            'url' => URL::temporarySignedRoute(
                'compartilhamentos.show',
                now()->addYears(10),
                ['compartilhamento' => $compartilhamento->id],
            ),
            'expires_at' => $compartilhamento->expires_at->toIso8601String(),
            'allow_download' => $compartilhamento->allow_download,
        ];

        if ($compartilhamento->allow_download) {
            $data['download_url'] = URL::temporarySignedRoute(
                'compartilhamentos.download',
                now()->addYears(10),
                ['compartilhamento' => $compartilhamento->id, 'formato' => 'bpmn'],
            );
        }

        return $data;
    }
}
