<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A Diagram's Lock (CONTEXT.md, "Lock"; ADR-0003, ADR-0009,
 * ADR-0011): who holds it, the Heartbeat that keeps it alive along with the
 * holder's unsaved draft, and the ways it changes hands or goes away.
 *
 * These are the primitives only. Who may ask for any of them is LockPolicy's
 * call, and when to call which is LockService's.
 */
trait HoldsALock
{
    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /** @return BelongsTo<User, $this> */
    public function lockRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lock_requested_by');
    }

    /**
     * True while a User holds the Diagram's Lock. A Lock is released
     * explicitly or, once it goes without a Heartbeat for the configured TTL,
     * by `locks:expire` (ADR-0003) — until then it counts as held.
     */
    public function hasActiveLock(): bool
    {
        return $this->locked_by !== null;
    }

    public function isLockedByOther(User $user): bool
    {
        return $this->hasActiveLock() && (int) $this->locked_by !== $user->id;
    }

    /**
     * True when $user actually holds the Lock right now. Deliberately not the
     * negation of {@see isLockedByOther()}: both are false for a free Lock,
     * which is nobody's. Saving content gates on this (then falls back to
     * {@see claimFreeLock()}), not on the mere absence of another holder — an
     * expired Lock must not let a stale Editor tab keep writing (ADR-0003).
     */
    public function isLockedBy(User $user): bool
    {
        return $this->hasActiveLock() && (int) $this->locked_by === $user->id;
    }

    /**
     * The columns that say "this Lock is held by $user, as of now". One
     * definition for every path that takes a Lock, so a future Lock column
     * can't be added to some of them and forgotten in the rest.
     *
     * @return array<string, mixed>
     */
    private function lockHeldBy(User $user): array
    {
        return [
            'locked_by' => $user->id,
            'locked_at' => now(),
            'last_heartbeat_at' => now(),
            'lock_draft' => null,
        ];
    }

    /**
     * This Diagram's row through the base query builder. Not the Eloquent
     * builder: that stamps updated_at on every update(), which the Diagram
     * list reports as "Modified" (issue #220). Opening the Editor, a
     * Heartbeat or a blocked request is not a change to the Diagram.
     */
    private function lockRow(): QueryBuilder
    {
        return DB::table($this->getTable())->where($this->getKeyName(), $this->getKey());
    }

    /**
     * Writes Lock columns to the row and mirrors them onto the model.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persistLockState(array $attributes): void
    {
        $this->lockRow()->update($attributes);

        $this->forceFill($attributes)->syncOriginalAttributes(array_keys($attributes));
    }

    public function acquireLock(User $user): void
    {
        $this->persistLockState($this->lockHeldBy($user));
    }

    /**
     * Takes a *free* Lock in one conditional write, reporting whether it was
     * won. Unlike {@see acquireLock()}, two concurrent callers can't both
     * succeed: the `locked_by is null` predicate is evaluated by the
     * database, so the loser's UPDATE matches no row. Used where the Lock is
     * a precondition rather than the point of the request (saving content).
     */
    public function claimFreeLock(User $user): bool
    {
        $held = $this->lockHeldBy($user);

        $won = $this->lockRow()
            ->whereNull('locked_by')
            ->update($held) === 1;

        if ($won) {
            // The row now *is* this, so nothing stays pending for a later
            // save() to rewrite.
            $this->forceFill($held)->syncOriginal();
        }

        return $won;
    }

    /**
     * Updates the Lock's heartbeat. Only has an effect if the given User is
     * the current lock holder. Any heartbeat counts as the holder
     * "responding", cancelling a pending transfer request.
     */
    public function heartbeat(User $user, ?string $draft = null): bool
    {
        if ((int) $this->locked_by !== $user->id) {
            return false;
        }

        $attributes = [
            'last_heartbeat_at' => now(),
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ];

        if ($draft !== null) {
            $attributes['lock_draft'] = $draft;
        }

        $this->persistLockState($attributes);

        return true;
    }

    /**
     * Drops the holder's unsaved-content draft once it has been saved for
     * real. A plain query update so the Diagram's "last modified" stays put.
     */
    public function clearLockDraft(): void
    {
        $this->lockRow()->update(['lock_draft' => null]);
        $this->setAttribute('lock_draft', null);
    }

    /**
     * Releases the Lock. If a User is given, only releases it if they're the
     * current lock holder (silent no-op otherwise).
     */
    public function releaseLock(?User $user = null): bool
    {
        if ($user !== null && (int) $this->locked_by !== $user->id) {
            return false;
        }

        $this->persistLockState([
            'locked_by' => null,
            'locked_at' => null,
            'last_heartbeat_at' => null,
            'lock_draft' => null,
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ]);

        return true;
    }

    /**
     * Hands the Lock straight to $newHolder in a single write, dropping any
     * pending request (a Transfer answers it, or pre-empts it).
     */
    public function transferLockTo(User $newHolder): void
    {
        $this->persistLockState($this->lockHeldBy($newHolder) + [
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ]);
    }

    /**
     * True if there's a pending Lock request made by $user.
     */
    public function pendingLockRequestIsFrom(User $user): bool
    {
        return $this->lock_requested_by !== null && (int) $this->lock_requested_by === $user->id;
    }

    /**
     * Registers $user as requesting the Lock. Doesn't restart the timestamp
     * if there's already a pending request from the same User.
     */
    public function registerLockRequest(User $user): void
    {
        if ($this->pendingLockRequestIsFrom($user)) {
            return;
        }

        $this->persistLockState([
            'lock_requested_by' => $user->id,
            'lock_requested_at' => now(),
        ]);
    }
}
