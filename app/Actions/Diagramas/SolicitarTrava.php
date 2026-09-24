<?php

namespace App\Actions\Diagramas;

use App\Models\Diagrama;
use App\Models\User;

/**
 * Orquestra um pedido de Trava de Edição: adquire direto se não houver Trava
 * ativa, transfere automaticamente se o detentor não respondeu dentro de
 * Diagrama::LOCK_REQUEST_TRANSFER_SECONDS, ou registra a solicitação e
 * devolve "aguardando" enquanto o prazo não vence (CONTEXT.md, "Trava de
 * Edição").
 */
class SolicitarTrava
{
    /**
     * @return array{estado: 'obtida'|'aguardando', expira_em?: string}
     */
    public function __invoke(Diagrama $diagrama, User $user): array
    {
        if (! $diagrama->hasActiveLock() || (int) $diagrama->locked_by === $user->id) {
            $diagrama->acquireLock($user);

            return ['estado' => 'obtida'];
        }

        if ($diagrama->pendingLockRequestIsFrom($user) && $diagrama->lockRequestTransferIsDue()) {
            $diagrama->transferLockTo($user);

            return ['estado' => 'obtida'];
        }

        $diagrama->registerLockRequest($user);

        return [
            'estado' => 'aguardando',
            'expira_em' => $diagrama->lock_requested_at
                ->addSeconds(Diagrama::LOCK_REQUEST_TRANSFER_SECONDS)
                ->toIso8601String(),
        ];
    }
}
