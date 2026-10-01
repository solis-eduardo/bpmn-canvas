<?php

namespace App\Actions\Diagrams;

use App\Models\Diagram;
use App\Models\User;

/**
 * Orchestrates an Editing Lock request: acquires it directly if the Lock is
 * free, or registers the request and returns "waiting" while another User
 * holds it. Requests never transfer a Lock; only the holder or an
 * Administrator can release it (CONTEXT.md, "Editing Lock").
 */
class RequestLock
{
    /**
     * @return array{state: 'acquired'|'waiting', requested_at?: string}
     */
    public function __invoke(Diagram $diagram, User $user): array
    {
        if (! $diagram->hasActiveLock() || (int) $diagram->locked_by === $user->id) {
            $diagram->acquireLock($user);

            return ['state' => 'acquired'];
        }

        $diagram->registerLockRequest($user);

        // "requested_at" is the server's own clock reading for this request
        // (not necessarily this call's time — it keeps the original timestamp
        // if the same User already has a pending request).
        return [
            'state' => 'waiting',
            'requested_at' => $diagram->lock_requested_at->toIso8601String(),
        ];
    }
}
