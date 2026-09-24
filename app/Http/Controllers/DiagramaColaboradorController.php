<?php

namespace App\Http\Controllers;

use App\Actions\Diagramas\ConcederOuSolicitarColaborador;
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
     * ou solicitação do próprio Usuário (status "solicitado") — ver
     * ConcederOuSolicitarColaborador para a regra completa.
     */
    public function store(Request $request, string $slug, ConcederOuSolicitarColaborador $conceder): JsonResponse
    {
        $diagrama = $this->resolveDiagrama($slug);

        $data = $request->validate([
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email'],
        ]);

        $alvo = isset($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : User::where('email', $data['email'])->firstOrFail();

        $colaborador = $conceder($diagrama, $request->user(), $alvo);

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
