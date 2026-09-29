<?php

namespace App\Models\Concerns;

use App\Models\DiagramCollaborator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Diagram list filter scopes (issue #39, "Filters and pagination on the
 * Diagram list"): "I'm the Diagram Owner", "I'm a Collaborator", "locked
 * right now", and "recently modified".
 */
trait FiltersDiagramList
{
    /**
     * Filters to Diagrams whose Diagram Owner is $user (issue #39, "I'm the
     * Diagram Owner").
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_id', $user->id);
    }

    /**
     * Filters to Diagrams where $user is an approved Collaborator (issue
     * #39, "I'm a Collaborator") — a pending request doesn't count.
     */
    public function scopeCollaboratedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('collaborators', fn ($collaborators) => $collaborators
            ->where('user_id', $user->id)
            ->where('status', DiagramCollaborator::STATUS_APPROVED));
    }

    /**
     * Filters to Diagrams currently under an active Editing Lock (issue #39,
     * "locked right now") — the query-level equivalent of hasActiveLock(),
     * since that method needs a loaded model to evaluate in PHP. Keep the
     * reference-timestamp rule (last_heartbeat_at, falling back to
     * locked_at) in sync with hasActiveLock().
     */
    public function scopeLockedNow(Builder $query): Builder
    {
        $threshold = now()->subSeconds(self::LOCK_INACTIVITY_TIMEOUT_SECONDS);

        return $query->whereNotNull('locked_by')
            ->whereRaw('COALESCE(last_heartbeat_at, locked_at) > ?', [$threshold]);
    }

    /**
     * Filters to Diagrams modified within the last $days days (issue #39,
     * "recently modified").
     */
    public function scopeRecentlyModified(Builder $query, int $days = 7): Builder
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }
}
