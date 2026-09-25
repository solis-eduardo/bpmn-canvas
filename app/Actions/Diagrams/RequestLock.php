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
     * @return array{state: 'acquired'|'waiting', requested_at?: string, expires_at?: string}
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

        // "requested_at" is the server's own clock reading for this request
        // (not necessarily *this* call's time — it's a no-op, and keeps the
        // original timestamp, if the same User already had a pending
        // request). Exposed so the client can derive its 30-second countdown
        // from the server's clock instead of trusting its own (issue #9).
        return [
            'state' => 'waiting',
            'requested_at' => $diagram->lock_requested_at->toIso8601String(),
            'expires_at' => $diagram->lock_requested_at
                ->addSeconds(Diagram::LOCK_REQUEST_TRANSFER_SECONDS)
                ->toIso8601String(),
        ];
    }
}
