<?php

namespace App\Actions\Diagrams;

use App\Models\Diagram;
use App\Models\User;

/**
 * Orchestrates an Editing Lock request: acquires it directly if there's no
 * active Lock, transfers it automatically if the holder hasn't responded
 * within Diagram::LOCK_REQUEST_TRANSFER_SECONDS, or registers the request
 * and returns "waiting" while the deadline hasn't passed (CONTEXT.md,
 * "Editing Lock").
 */
class RequestLock
{
    /**
     * @return array{state: 'acquired'|'waiting', expires_at?: string}
     */
    public function __invoke(Diagram $diagram, User $user): array
    {
        if (! $diagram->hasActiveLock() || (int) $diagram->locked_by === $user->id) {
            $diagram->acquireLock($user);

            return ['state' => 'acquired'];
        }

        if ($diagram->pendingLockRequestIsFrom($user) && $diagram->lockRequestTransferIsDue()) {
            $diagram->transferLockTo($user);

            return ['state' => 'acquired'];
        }

        $diagram->registerLockRequest($user);

        return [
            'state' => 'waiting',
            'expires_at' => $diagram->lock_requested_at
                ->addSeconds(Diagram::LOCK_REQUEST_TRANSFER_SECONDS)
                ->toIso8601String(),
        ];
    }
}
