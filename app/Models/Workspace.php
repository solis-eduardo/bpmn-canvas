<?php

namespace App\Models;

use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of Users (its Members) that holds Diagrams (CONTEXT.md,
 * "Workspace"), optionally mirroring Members from a Keycloak group.
 */
#[Fillable(['name', 'owner_id', 'keycloak_group'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * The Workspace Owner (CONTEXT.md, "Workspace Owner"): the single User
     * who manages this Workspace. Starts as the creator.
     */
    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<WorkspaceMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * Diagrams currently placed in this Workspace (see issue #27; only the
     * column exists so far — put-in/take-out isn't implemented yet).
     */
    /** @return HasMany<Diagram, $this> */
    public function diagrams(): HasMany
    {
        return $this->hasMany(Diagram::class);
    }

    /**
     * True if this Workspace still holds at least one Diagram. A Workspace
     * can't be deleted while this is true (CONTEXT.md, "Workspace Owner").
     */
    public function hasDiagrams(): bool
    {
        return $this->diagrams()->exists();
    }

    /**
     * True if $user is this Workspace's current Workspace Owner.
     */
    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === $user->id;
    }

    /**
     * True if $user is a Member of this Workspace (including the Workspace
     * Owner, who is also a Member).
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Adds $user as a Member. Silently does nothing if already a Member —
     * callers don't need to check first.
     */
    public function addMember(User $user, bool $notify = true): void
    {
        $member = $this->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['origin' => WorkspaceMember::ORIGIN_MANUAL],
        );

        if ($notify && $member->wasRecentlyCreated) {
            $user->notify(new WorkspaceMemberAdded($this));
        }
    }

    /**
     * Adds a group-managed Member without changing any existing manual grant.
     */
    public function addGroupMember(User $user, bool $notify = true): void
    {
        if ($this->hasMember($user)) {
            return;
        }

        $this->members()->create([
            'user_id' => $user->id,
            'origin' => WorkspaceMember::ORIGIN_GROUP,
        ]);

        if ($notify) {
            $user->notify(new WorkspaceMemberAdded($this));
        }
    }

    /**
     * Removes $user as a Member. Returns false without effect if they
     * weren't a Member.
     */
    public function removeMember(User $user, bool $notify = true): bool
    {
        $removed = $this->members()->where('user_id', $user->id)->delete() > 0;

        if ($removed && $notify) {
            $user->notify(new WorkspaceMemberRemoved($this));
        }

        return $removed;
    }
}
