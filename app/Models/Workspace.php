<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'owner_id'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * The Workspace Owner (CONTEXT.md, "Workspace Owner"; ADR-0009): the
     * single User who manages this Workspace. Starts as the creator and,
     * unlike a Diagram's Author, is transferable (future issue) without
     * needing acceptance.
     */
    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * True if $user is this Workspace's current Workspace Owner. Every
     * permission check ("update", "manageMembers") should use this instead
     * of comparing owner_id directly, so a future ownership-transfer ticket
     * only has to touch this one place.
     */
    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === $user->id;
    }

    /** @return HasMany<WorkspaceMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * The Workspace's Members (CONTEXT.md, "Member"). Unlike a Diagram's
     * Owner/Collaborator split (ADR-0006), the Workspace Owner is also
     * represented here — see ADR-0009 — so this relation alone answers "who
     * belongs to this Workspace".
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members');
    }

    /**
     * True if $user is a Member of this Workspace (which includes the
     * Workspace Owner — see ADR-0009). Checks the already-loaded `members`
     * relation when available, so callers that eager-load it (e.g.
     * WorkspaceController::show()) don't pay for a second query here.
     */
    public function hasMember(User $user): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains('id', $user->id);
        }

        return $this->memberships()->where('user_id', $user->id)->exists();
    }

    /**
     * Adds $user as a Member, unless already one. Used both when creating
     * the Workspace (the creator becomes the first Member) and when the
     * Workspace Owner/Administrator adds someone later.
     */
    public function addMember(User $user): WorkspaceMember
    {
        return $this->memberships()->firstOrCreate(['user_id' => $user->id]);
    }
}
