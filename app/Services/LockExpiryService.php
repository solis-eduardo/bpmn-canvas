<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\DiagramVersionName;
use App\Repositories\VersionRepository;
use Illuminate\Support\Carbon;

/**
 * Releases Locks whose holder stopped sending Heartbeats for the configured
 * TTL (ADR-0003). If the holder's Editor had unsaved content at its last
 * Heartbeat, that content is kept as a provisional Version before release.
 */
class LockExpiryService
{
    public function __construct(
        private readonly LockService $locks,
        private readonly VersionRepository $versions,
    ) {}

    /**
     * @return int Number of Locks released.
     */
    public function releaseExpired(): int
    {
        $cutoff = Carbon::now()->subMinutes(config('bpmn.lock_ttl_minutes'));

        $released = 0;

        Diagram::query()
            ->whereNotNull('locked_by')
            ->where(fn ($query) => $query
                ->where('last_heartbeat_at', '<=', $cutoff)
                ->orWhere(fn ($legacy) => $legacy->whereNull('last_heartbeat_at')->where('locked_at', '<=', $cutoff)))
            ->with('lockedBy')
            // chunkById, not each(): releasing clears `locked_by`, the very
            // column the filter selects on, so offset-based paging would
            // skip rows as the result set shrinks under it.
            ->chunkById(100, function ($diagrams) use (&$released): void {
                foreach ($diagrams as $diagram) {
                    $this->saveProvisionalVersion($diagram);
                    $this->locks->release($diagram);
                    $released++;
                }
            });

        return $released;
    }

    private function saveProvisionalVersion(Diagram $diagram): void
    {
        $holder = $diagram->lockedBy;

        if ($holder === null || blank($diagram->lock_draft)) {
            return;
        }

        // The draft was accepted while the holder could edit, but expiry
        // happens minutes later and rights can lapse in between. Committing
        // it would write content as a User who may no longer edit this
        // Diagram, so a lapsed holder's draft is dropped instead.
        if (! $diagram->isEditableBy($holder)) {
            return;
        }

        $versionId = $this->versions->save($diagram, $holder, $diagram->lock_draft, VersionRepository::MESSAGE_PROVISIONAL);

        if ($versionId !== null) {
            DiagramVersionName::updateOrCreate(
                ['diagram_id' => $diagram->id, 'version_id' => $versionId],
                ['name' => __('messages.provisional_version')],
            );
        }
    }
}
