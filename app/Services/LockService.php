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
    public function renew(Diagram $diagram, User $user, ?string $draft = null): bool
    {
        return $diagram->heartbeat($user, $draft);
    }

    /**
     * Heartbeat that also reports who, if anyone, was waiting for the Lock.
     * A heartbeat counts as the holder responding and clears the request, so
     * the requester has to be read first. $draft is the holder's unsaved
     * Editor content, kept for a provisional Version if the Lock expires.
     */
    public function heartbeat(Diagram $diagram, User $holder, ?string $draft = null): ?User
    {
        $diagram->loadMissing('lockRequestedBy');
        $requester = $diagram->lockRequestedBy;

        $this->renew($diagram, $holder, $draft);

        return $requester;
    }

    /**
     * Opens the Editor for $user: false when someone else holds the Lock.
     * Otherwise the Lock is theirs — a holder only gets the heartbeat
     * refreshed, so the original "locked since" stays.
     */
    public function enter(Diagram $diagram, User $user): bool
    {
        if ($diagram->isLockedByOther($user)) {
            return false;
        }

        if (! $this->renew($diagram, $user)) {
            $this->openOrRequest($diagram, $user);
        }

        return true;
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
