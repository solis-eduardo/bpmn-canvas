<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        return $this->belongsTo(User::class, 'owner_id')->withDeactivated();
    }

    /**
     * Whether $memberIds would drop an Owner who still has to be a Member. A
     * deactivated Owner can't act, so the picker no longer lists them and they
     * may be absent (ADR-0020).
     *
     * @param  Collection<int, int>  $memberIds
     */
    public function wouldDropOwner(Collection $memberIds): bool
    {
        return ! $memberIds->contains($this->owner_id) && ! $this->owner->isDeactivated();
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
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

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * True if $user may manage this Workspace: its Workspace Owner or an
     * Administrator.
     */
    public function isManagedBy(User $user): bool
    {
        return $this->isOwnedBy($user) || $user->is_admin;
    }

    /**
     * True if $user is this Workspace's current Workspace Owner.
     */
    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === $user->id;
    }
}
