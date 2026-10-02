<?php

namespace App\Models\Concerns;

use App\Models\Collaborator;
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
            ->where('status', Collaborator::STATUS_APPROVED));
    }

    /**
     * Filters to Diagrams currently under an active Editing Lock (issue #39,
     * "locked right now") — the query-level equivalent of hasActiveLock(),
     * since that method needs a loaded model to evaluate in PHP. Editing
     * Locks remain held until explicitly released, regardless of activity.
     */
    public function scopeLockedNow(Builder $query): Builder
    {
        return $query->whereNotNull('locked_by');
    }

    /**
     * Filters to Diagrams modified within the last $days days (issue #39,
     * "recently modified").
     */
    public function scopeRecentlyModified(Builder $query, int $days = 7): Builder
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * Filters to Diagrams carrying every one of the given Tag names (issues
     * #40 and #150). A name no Tag has matches nothing.
     *
     * @param  list<string>  $tagNames
     */
    public function scopeTaggedWithAll(Builder $query, array $tagNames): Builder
    {
        return $this->scopeWithAllTags($query, $tagNames);
    }
}
