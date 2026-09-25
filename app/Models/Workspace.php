<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of Users (its Members) that holds Diagrams (CONTEXT.md,
 * "Workspace"). This ticket (#26) only covers creating a Workspace and
 * managing its Members — putting Diagrams inside one is a later ticket.
 */
#[Fillable(['name', 'owner_id'])]
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
    public function addMember(User $user): void
    {
        if ($this->hasMember($user)) {
            return;
        }

        $this->members()->create(['user_id' => $user->id]);
    }

    /**
     * Removes $user as a Member. Returns false without effect if they
     * weren't a Member.
     */
    public function removeMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->delete() > 0;
    }
}
