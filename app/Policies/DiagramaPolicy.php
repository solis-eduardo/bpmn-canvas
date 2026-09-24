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

    /**
     * Gerenciar Colaboradores de um Diagrama (convidar/aprovar/remover):
     * Autor ou Administrador (spec.md, seção "Colaboração").
     */
    public function manageColaboradores(User $user, Diagrama $diagrama): bool
    {
        return $user->id === $diagrama->author_id || $user->is_admin;
    }

    /**
     * Gerenciar Compartilhamentos de um Diagrama (criar/revogar): só o Autor,
     * sem exceção para Administrador — "Somente o Autor cria e revoga
     * Compartilhamentos" (CONTEXT.md). Regra deliberadamente mais restrita
     * que manageColaboradores; não unificar as duas.
     */
    public function manageCompartilhamentos(User $user, Diagrama $diagrama): bool
    {
        return $user->id === $diagrama->author_id;
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
