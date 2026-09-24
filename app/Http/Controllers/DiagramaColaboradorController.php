<?php

namespace App\Http\Controllers;

use App\Models\Diagrama;
use App\Models\DiagramaColaborador;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagramaColaboradorController extends Controller
{
    /**
     * Autor (ou Administrador) lista os Colaboradores/solicitações de um Diagrama.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $diagrama = $this->resolveDiagrama($slug);

        $this->authorize('manageColaboradores', $diagrama);

        $colaboradores = $diagrama->colaboradores()->with('usuario')->get()->map(fn (DiagramaColaborador $colaborador) => [
            'usuario_id' => $colaborador->usuario_id,
            'nome' => $colaborador->usuario->name,
            'email' => $colaborador->usuario->email,
            'status' => $colaborador->status,
        ]);

        return response()->json($colaboradores);
    }

    /**
     * Convite direto do Autor/Administrador (acesso imediato, status "aprovado")
     * ou solicitação do próprio Usuário (status "solicitado").
     *
     * Regra de distinção: se quem faz a request é o Autor do Diagrama OU um
     * Administrador, a linha é gravada como "aprovado", não importa quem seja
     * o alvo. Caso contrário, só é permitido quando o alvo é o próprio
     * solicitante (auto-solicitação), gravada como "solicitado"; tentar
     * adicionar outra pessoa sem ser Autor/Administrador é bloqueado (403).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $diagrama = $this->resolveDiagrama($slug);

        $data = $request->validate([
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email'],
        ]);

        $alvo = isset($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : User::where('email', $data['email'])->firstOrFail();

        $solicitante = $request->user();
        $podeConcederDireto = $solicitante->can('manageColaboradores', $diagrama);

        if (! $podeConcederDireto && $solicitante->id !== $alvo->id) {
            abort(403);
        }

        $existente = DiagramaColaborador::where('diagrama_id', $diagrama->id)
            ->where('usuario_id', $alvo->id)
            ->first();

        // Uma auto-solicitação nunca rebaixa um colaborador já aprovado de volta
        // para "solicitado" — só concede direto (Autor/Admin) grava "aprovado";
        // auto-solicitação preserva o status aprovado existente, se houver.
        $status = $podeConcederDireto
            ? DiagramaColaborador::STATUS_APROVADO
            : ($existente->status ?? DiagramaColaborador::STATUS_SOLICITADO);

        $colaborador = DiagramaColaborador::updateOrCreate(
            ['diagrama_id' => $diagrama->id, 'usuario_id' => $alvo->id],
            ['status' => $status]
        );

        return response()->json([
            'usuario_id' => $colaborador->usuario_id,
            'status' => $colaborador->status,
        ], 201);
    }

    /**
     * Autor ou Administrador aprova uma solicitação pendente.
     */
    public function update(Request $request, string $slug, User $user): JsonResponse
    {
        $diagrama = $this->resolveDiagrama($slug);

        $this->authorize('manageColaboradores', $diagrama);

        $colaborador = DiagramaColaborador::where('diagrama_id', $diagrama->id)
            ->where('usuario_id', $user->id)
            ->firstOrFail();

        $colaborador->update(['status' => DiagramaColaborador::STATUS_APROVADO]);

        return response()->json([
            'usuario_id' => $colaborador->usuario_id,
            'status' => $colaborador->status,
        ]);
    }

    /**
     * Autor ou Administrador recusa uma solicitação pendente ou remove um
     * Colaborador já aprovado — em ambos os casos, a linha é apagada.
     */
    public function destroy(Request $request, string $slug, User $user): JsonResponse
    {
        $diagrama = $this->resolveDiagrama($slug);

        $this->authorize('manageColaboradores', $diagrama);

        DiagramaColaborador::where('diagrama_id', $diagrama->id)
            ->where('usuario_id', $user->id)
            ->delete();

        return response()->json(['deleted' => true]);
    }

    private function resolveDiagrama(string $slug): Diagrama
    {
        return Diagrama::where('slug', $slug)->firstOrFail();
    }
}
