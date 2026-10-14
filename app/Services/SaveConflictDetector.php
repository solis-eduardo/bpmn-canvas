<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;

/**
 * Optimistic concurrency for saves (issue #243): decides whether a save made
 * on top of the Version the Editor loaded ($base) would silently overwrite a
 * newer one.
 *
 * Not every newer Version is someone else's work. When the Lock holder's own
 * Lock expired, their unsaved content was kept as a provisional Version in
 * their name (ADR-0003); their still-open Editor then saves over a Version that
 * came from its own draft. That is no conflict: nothing but their own content
 * would be superseded, and it stays in the history.
 */
class SaveConflictDetector
{
    public function __construct(private readonly VersionRepository $versions) {}

    /**
     * The id of the current Version when saving on top of $base would
     * overwrite a newer Version made by someone else; null when the save may
     * proceed.
     */
    public function conflictingVersion(Diagram $diagram, User $user, ?string $base): ?string
    {
        $current = $this->versions->currentVersionId($diagram);

        if ($base === $current) {
            return null;
        }

        return $this->onlyOwnKeptDraftsSince($diagram, $user, $base) ? null : $current;
    }

    /**
     * True when every Version newer than $base is a provisional Version that
     * kept this User's own unsaved content. Only reached once the base is
     * outdated, so the (costlier) history is read on that path alone.
     */
    private function onlyOwnKeptDraftsSince(Diagram $diagram, User $user, ?string $base): bool
    {
        if ($base === null) {
            return false;
        }

        foreach ($this->versions->list($diagram) as $version) {
            if ($version['id'] === $base) {
                return true;
            }

            if (! $version['provisional'] || $version['kept_for'] !== $user->id) {
                return false;
            }
        }

        // The base is not in this Diagram's history at all.
        return false;
    }
}
