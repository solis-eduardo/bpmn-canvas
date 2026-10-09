<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;
use App\Notifications\DiagramLockRequested;
use Illuminate\Support\Facades\DB;

/**
 * Decides which Lock primitive on the Diagram to call, and when (CONTEXT.md,
 * "Lock"). Never decides who is allowed to ask: that is LockPolicy, checked
 * by the caller before getting here.
 */
class LockService
{
    public function __construct(private readonly ProvisionalVersionService $provisional) {}

    public function state(Diagram $diagram): LockState
    {
        return new LockState($diagram->lockedBy, $diagram->lockRequestedBy, $diagram->lock_requested_at, filled($diagram->lock_draft));
    }

    /**
     * Acquires the Lock when it is free or already the User's; otherwise
     * registers a request and reports "waiting". A request never moves the
     * Lock by itself.
     */
    public function openOrRequest(Diagram $diagram, User $user): LockOutcome
    {
        if (! $diagram->isLockedByOther($user)) {
            $diagram->acquireLock($user);

            return LockOutcome::acquired();
        }

        $alreadyPending = $diagram->pendingLockRequestIsFrom($user);

        $diagram->registerLockRequest($user);

        // Re-opening the blocked screen keeps polling this endpoint, and the
        // holder's Heartbeat clears the pending request in between, so the
        // pending request alone can't tell a repeat from a new request: an
        // unread notification from the same requester can.
        if (! $alreadyPending && ! $this->holderAlreadyNotified($diagram, $user)) {
            $diagram->lockedBy?->notify(new DiagramLockRequested($diagram, $user));
        }

        return LockOutcome::waiting($diagram->lock_requested_at);
    }

    /**
     * True while the holder still has an unread notification from $requester
     * asking for this Diagram's Lock.
     */
    private function holderAlreadyNotified(Diagram $diagram, User $requester): bool
    {
        $unread = $diagram->lockedBy?->unreadNotifications()
            ->where('type', DiagramLockRequested::class)
            ->get();

        return $unread?->contains(fn ($notification) => ($notification->data['diagram_id'] ?? null) === $diagram->id
            && ($notification->data['requester_id'] ?? null) === $requester->id) ?? false;
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

    /**
     * Releases the Lock, dropping the holder's draft unless $saveDraft asks
     * to keep it as a provisional Version first ($draft being the holder's
     * latest Editor content, newer than what the last Heartbeat carried).
     * The Version is written before the Lock goes, so a failed save leaves
     * the Lock and the draft untouched.
     */
    public function release(Diagram $diagram, bool $saveDraft = false, ?string $draft = null): void
    {
        // In a transaction so a release that fails after the save has
        // cleared the draft doesn't leave a held Lock with its draft gone.
        DB::transaction(function () use ($diagram, $saveDraft, $draft): void {
            if ($saveDraft) {
                $this->provisional->save($diagram, $draft);
            }

            $diagram->releaseLock();
        });
    }
}
