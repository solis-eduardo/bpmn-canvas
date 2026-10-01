<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;

/**
 * Decides which Lock primitive on the Diagram to call, and when (CONTEXT.md,
 * "Lock"). Never decides who is allowed to ask: that is LockPolicy, checked
 * by the caller before getting here.
 */
class LockService
{
    public function state(Diagram $diagram): LockState
    {
        return new LockState($diagram->lockedBy, $diagram->lockRequestedBy, $diagram->lock_requested_at);
    }

    /**
     * Acquires the Lock when it is free or already the User's; otherwise
     * registers a request and reports "waiting". A request never moves the
     * Lock by itself.
     */
    public function openOrRequest(Diagram $diagram, User $user): LockOutcome
    {
        if (! $diagram->hasActiveLock() || ! $diagram->isLockedByOther($user)) {
            $diagram->acquireLock($user);

            return LockOutcome::acquired();
        }

        $diagram->registerLockRequest($user);

        return LockOutcome::waiting($diagram->lock_requested_at);
    }

    /**
     * Heartbeat: only has an effect for the current holder.
     */
    public function renew(Diagram $diagram, User $user): bool
    {
        return $diagram->heartbeat($user);
    }

    /**
     * Hands the Lock to $newHolder (ADR-0011), whether or not they asked
     * for it first.
     */
    public function transfer(Diagram $diagram, User $newHolder): void
    {
        $diagram->transferLockTo($newHolder);
    }

    public function release(Diagram $diagram): void
    {
        $diagram->releaseLock();
    }
}
