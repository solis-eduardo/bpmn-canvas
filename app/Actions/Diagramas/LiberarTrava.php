<?php

namespace App\Actions\Diagramas;

use App\Models\Diagrama;
use App\Models\User;

/**
 * Libera a Trava de Edição: o próprio detentor libera normalmente; um
 * Administrador pode forçar a liberação mesmo sem ser o detentor, "como
 * alternativa ao mecanismo dos 30 segundos" (CONTEXT.md, "Trava de
 * Edição"). Sem Trava ativa, é um no-op bem-sucedido.
 */
class LiberarTrava
{
    public function __invoke(Diagrama $diagrama, User $user): bool
    {
        if ($diagrama->locked_by === null) {
            return true;
        }

        if ((int) $diagrama->locked_by === $user->id) {
            return $diagrama->releaseLock($user);
        }

        return $user->is_admin && $diagrama->releaseLock();
    }
}
