<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
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
        if ($diagram->acquireLock($user)) {
            return LockOutcome::acquired();
        }

        // Someone else won the Lock after this request loaded the Diagram.
        $diagram->refresh();

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

        if ($this->renew($diagram, $user)) {
            return true;
        }

        if ($diagram->acquireLock($user)) {
            return true;
        }

        // Lost the race for a free Lock: report it as held by someone else.
        $diagram->refresh();

        return false;
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

    /**
     * Releases the Lock when its holder can no longer edit the Diagram. Called
     * after every change that can take edit rights away (CONTEXT.md, "Lock"),
     * so a former editor never keeps showing as the holder. The holder's draft
     * is dropped, not kept as a provisional Version: that Version would be
     * authored by someone who may no longer edit (ProvisionalVersionService).
     */
    public function reconcile(Diagram $diagram): void
    {
        $diagram->refresh();

        $this->releaseIfLapsed($diagram);
    }

    /**
     * Reconciles every Lock $user holds on a Diagram in $workspace, or in any
     * Workspace when none is given. Membership changes can cost a User edit
     * rights on several Diagrams at once, so they go through here. The rows
     * are fresh from the query and the holder is eager-loaded, so this skips
     * the per-Diagram refresh and holder lookup that reconcile() does.
     */
    public function reconcileHeldBy(User $user, ?Workspace $workspace = null): void
    {
        Diagram::query()
            ->where('locked_by', $user->id)
            ->with('lockedBy')
            ->whereNotNull('workspace_id')
            ->when($workspace !== null, fn ($query) => $query->where('workspace_id', $workspace?->id))
            ->each(fn (Diagram $diagram) => $this->releaseIfLapsed($diagram));
    }

    /**
     * Releases the Lock if its (already loaded) holder can no longer edit.
     */
    private function releaseIfLapsed(Diagram $diagram): void
    {
        $holder = $diagram->lockedBy;

        if ($holder === null || $diagram->isEditableBy($holder)) {
            return;
        }

        $this->release($diagram);
    }
}
