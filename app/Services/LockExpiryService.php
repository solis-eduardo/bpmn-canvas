<?php

namespace App\Services;

use App\Exceptions\VersionHistoryBusyException;
use App\Models\Diagram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Releases Locks whose holder stopped sending Heartbeats for the configured
 * TTL (ADR-0003). If the holder's Editor had unsaved content at its last
 * Heartbeat, that content is kept as a provisional Version before release.
 */
class LockExpiryService
{
    public function __construct(
        private readonly LockService $locks,
    ) {}

    /**
     * @return int Number of Locks released.
     */
    public function releaseExpired(): int
    {
        $cutoff = Carbon::now()->subMinutes(config('bpmn.lock_ttl_minutes'));

        $released = 0;

        $this->expiredBefore($cutoff)
            ->select('id')
            // chunkById, not each(): releasing clears `locked_by`, the very
            // column the filter selects on, so offset-based paging would
            // skip rows as the result set shrinks under it.
            ->chunkById(100, function ($diagrams) use ($cutoff, &$released): void {
                foreach ($diagrams as $candidate) {
                    if ($this->tryRelease($candidate->getKey(), $cutoff)) {
                        $released++;
                    }
                }
            });

        return $released;
    }

    /**
     * Releases one Diagram, or skips it when its Version history stayed busy
     * for the whole wait (issue #242). The release is rolled back, so the Lock
     * and the draft are untouched and the next run (a minute later) retries;
     * one Diagram must not abort the run for the others.
     */
    private function tryRelease(int|string $id, Carbon $cutoff): bool
    {
        try {
            return $this->releaseIfStillExpired($id, $cutoff);
        } catch (VersionHistoryBusyException $busy) {
            Log::warning('Lock expiry skipped a Diagram: its Version history is busy; the Lock stays for the next run.', [
                'diagram_id' => $id,
            ]);

            return false;
        }
    }

    /**
     * Re-reads the Diagram under a row lock and releases it only if it is
     * still expired. The chunk was selected earlier, so a Heartbeat or a save
     * may have landed in between; releasing from that stale row would take the
     * Lock from an active holder or stack a provisional Version over a real
     * save. The lock makes a concurrent Heartbeat wait, then it is seen.
     */
    private function releaseIfStillExpired(int|string $id, Carbon $cutoff): bool
    {
        return DB::transaction(function () use ($id, $cutoff): bool {
            $diagram = $this->expiredBefore($cutoff)
                ->whereKey($id)
                ->lockForUpdate()
                ->with('lockedBy')
                ->first();

            if ($diagram === null) {
                return false;
            }

            $this->locks->release($diagram, saveDraft: true);

            return true;
        });
    }

    /**
     * Diagrams held by someone whose last Heartbeat is at or before $cutoff
     * (or, for Locks from before Heartbeats existed, whose Lock was taken then).
     *
     * @return Builder<Diagram>
     */
    private function expiredBefore(Carbon $cutoff): Builder
    {
        return Diagram::query()
            ->whereNotNull('locked_by')
            ->where(fn ($query) => $query
                ->where('last_heartbeat_at', '<=', $cutoff)
                ->orWhere(fn ($legacy) => $legacy->whereNull('last_heartbeat_at')->where('locked_at', '<=', $cutoff)));
    }
}
