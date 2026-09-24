<?php

namespace App\Http\Controllers;

use App\Actions\Diagramas\LiberarTrava;
use App\Actions\Diagramas\SolicitarTrava;
use App\Models\Diagrama;
use App\Models\DiagramaSlugAntigo;
use App\Repositories\DiagramaRepository;
use App\Services\VersaoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DiagramaController extends Controller
{
    public function index(): View
    {
        $diagramas = Diagrama::with(['author', 'lockedBy'])->orderBy('title')->get();

        return view('diagramas.index', compact('diagramas'));
    }

    public function store(Request $request, DiagramaRepository $diagramas, VersaoRepository $versoes): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $slug = Diagrama::uniqueSlug($data['title']);

        $diagrama = $diagramas->criar([
            'title' => $data['title'],
            'slug' => $slug,
            'author_id' => $request->user()->id,
            'disk_path' => $slug.'/diagram.bpmn',
        ], Diagrama::EMPTY_BPMN);

        $versoes->inicializar($diagrama, $request->user());

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

    public function save(Request $request, string $slug, VersaoRepository $versoes): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $this->authorize('update', $diagrama);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $versoes->inicializar($diagrama, $request->user());

        Storage::disk('diagrams')->put($diagrama->disk_path, $data['content']);

        $versoes->salvar($diagrama, $request->user());

        return response()->json(['saved' => true]);
    }

    public function versoes(string $slug, VersaoRepository $versoes): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $this->authorize('view', $diagrama);

        $agrupado = collect($versoes->listar($diagrama))
            ->groupBy(fn (array $versao) => $versao['salvo_em']->toDateString())
            ->map(fn ($itens, string $dia) => [
                'dia' => $dia,
                'itens' => $itens->map(fn (array $versao) => [
                    'id' => $versao['id'],
                    'salvo_em' => $versao['salvo_em']->toIso8601String(),
                    'autor' => $versao['autor'],
                ])->values(),
            ])
            ->values();

        return response()->json(['versoes' => $agrupado]);
    }

    public function restaurarVersao(Request $request, string $slug, string $versao, VersaoRepository $versoes): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $this->authorize('update', $diagrama);

        try {
            $versoes->restaurar($diagrama, $versao, $request->user());
        } catch (\RuntimeException $e) {
            abort(404);
        }

        return response()->json(['restaurado' => true]);
    }

    public function rename(Request $request, string $slug, DiagramaRepository $diagramas): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $newSlug = Diagrama::uniqueSlug($data['title'], $diagrama->id);

        $diagrama = $diagramas->mover($diagrama, $data['title'], $newSlug);

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

    public function unlock(Request $request, string $slug, LiberarTrava $liberarTrava): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        if (! $liberarTrava($diagrama, $request->user())) {
            return response()->json(['message' => 'Você não detém a trava deste diagrama.'], 403);
        }

        return response()->json(['unlocked' => true]);
    }

    /**
     * Solicita a Trava de um Diagrama travado por outro Usuário. Devolve
     * "obtida" se a Trava foi adquirida (direto, ou por transferência
     * automática após LOCK_REQUEST_TRANSFER_SECONDS sem resposta do
     * detentor) ou "aguardando" enquanto o prazo não vence — o cliente deve
     * chamar de novo em polling até obter "obtida".
     */
    public function solicitarTrava(Request $request, string $slug, SolicitarTrava $solicitarTrava): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $this->authorize('view', $diagrama);

        return response()->json($solicitarTrava($diagrama, $request->user()));
    }

    public function fork(Request $request, string $slug, DiagramaRepository $diagramas): RedirectResponse
    {
        [$diagrama] = $this->resolve($slug);

        $this->authorize('view', $diagrama);

        $newSlug = Diagrama::uniqueSlug($diagrama->title);

        $forked = $diagramas->criar([
            'title' => $diagrama->title,
            'slug' => $newSlug,
            'author_id' => $request->user()->id,
            'disk_path' => $newSlug.'/diagram.bpmn',
            'forked_from_id' => $diagrama->id,
        ], Storage::disk('diagrams')->get($diagrama->disk_path));

        return redirect()->route('diagramas.show', $forked->slug);
    }

    public function destroy(string $slug, DiagramaRepository $diagramas): JsonResponse
    {
        [$diagrama] = $this->resolve($slug);

        $diagramas->remover($diagrama);

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
