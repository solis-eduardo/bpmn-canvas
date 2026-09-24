<?php

namespace App\Policies;

use App\Models\Diagrama;
use App\Models\DiagramaColaborador;
use App\Models\User;

class DiagramaPolicy
{
    /**
     * Abrir o Editor para editar um Diagrama: Autor ou Colaborador aprovado.
     */
    public function view(User $user, Diagrama $diagrama): bool
    {
        return $this->podeEditar($user, $diagrama);
    }

    /**
     * Salvar o conteúdo de um Diagrama: Autor ou Colaborador aprovado.
     */
    public function update(User $user, Diagrama $diagrama): bool
    {
        return $this->podeEditar($user, $diagrama);
    }

    private function podeEditar(User $user, Diagrama $diagrama): bool
    {
        if ($user->id === $diagrama->author_id) {
            return true;
        }

        return DiagramaColaborador::where('diagrama_id', $diagrama->id)
            ->where('usuario_id', $user->id)
            ->where('status', DiagramaColaborador::STATUS_APROVADO)
            ->exists();
    }
}
