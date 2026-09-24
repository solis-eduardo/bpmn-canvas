<?php

namespace App\Actions\Diagramas;

use App\Models\Diagrama;
use App\Models\DiagramaColaborador;
use App\Models\User;

/**
 * Convite direto do Autor/Administrador (acesso imediato, status "aprovado")
 * ou auto-solicitação do próprio Usuário (status "solicitado"): se quem
 * solicita é o Autor ou um Administrador, a linha é gravada como "aprovado",
 * não importa quem seja o alvo; caso contrário, só é permitido quando o
 * alvo é o próprio solicitante, e uma auto-solicitação nunca rebaixa um
 * Colaborador já aprovado de volta para "solicitado".
 */
class ConcederOuSolicitarColaborador
{
    public function __invoke(Diagrama $diagrama, User $solicitante, User $alvo): DiagramaColaborador
    {
        $podeConcederDireto = $solicitante->can('manageColaboradores', $diagrama);

        if (! $podeConcederDireto && $solicitante->id !== $alvo->id) {
            abort(403);
        }

        $existente = DiagramaColaborador::where('diagrama_id', $diagrama->id)
            ->where('usuario_id', $alvo->id)
            ->first();

        $status = $podeConcederDireto
            ? DiagramaColaborador::STATUS_APROVADO
            : ($existente->status ?? DiagramaColaborador::STATUS_SOLICITADO);

        return DiagramaColaborador::updateOrCreate(
            ['diagrama_id' => $diagrama->id, 'usuario_id' => $alvo->id],
            ['status' => $status]
        );
    }
}
