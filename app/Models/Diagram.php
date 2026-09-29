<?php

namespace App\Models;

use App\Models\Concerns\FiltersDiagramList;
use App\Models\Concerns\HasTags;
use App\Support\Slugger;
use Database\Factories\DiagramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['slug', 'title', 'author_id', 'owner_id', 'workspace_id', 'disk_path', 'forked_from_id'])]
class Diagram extends Model
{
    use FiltersDiagramList;
    /** @use HasFactory<DiagramFactory> */
    use HasFactory;

    use HasTags;

    /**
     * Template for a newly created Diagram's seed BPMN XML. The start
     * event's label is filled in per the current locale by emptyBpmn()
     * (see "messages.start_event_label"), instead of being fixed here.
     */
    private const EMPTY_BPMN_TEMPLATE = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="%s" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="180" y="160" width="36" height="36" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>
BPMN;

    /**
     * Seed BPMN XML for a newly created Diagram, with the start event's
     * label localized to the current locale.
     */
    public static function emptyBpmn(): string
    {
        $label = htmlspecialchars(__('messages.start_event_label'), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return sprintf(self::EMPTY_BPMN_TEMPLATE, $label);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'lock_requested_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The Diagram Owner (CONTEXT.md, "Diagram Owner"; ADR-0006): the User
     * currently responsible for this Diagram. Starts as the Author but,
     * unlike authorship, is transferable (see issue #14). Every permission
     * check that used to gate on "is the Author" now gates on
     * {@see isOwnedBy()} instead.
     */
    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * True if $user is this Diagram's current Diagram Owner. The single
     * helper every permission check ("delete", "manageCollaborators",
     * "manageShares", editing) should use instead of comparing author_id
     * directly — keeps future tickets (ownership transfer, etc.) from
     * having to touch each call site again.
     */
    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === $user->id;
    }

    /**
     * The Workspace this Diagram belongs to, if any (CONTEXT.md,
     * "Workspace"). A Diagram belongs to at most one.
     */
    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Diagram, $this> */
    public function forkedFrom(): BelongsTo
    {
        return $this->belongsTo(Diagram::class, 'forked_from_id');
    }

    /**
     * True if $user can edit this Diagram: Diagram Owner, an approved
     * Collaborator, or (CONTEXT.md, "Member"; issue #27) a Member of the
     * Diagram's Workspace, checked live so removal from the Workspace takes
     * effect immediately. The single helper behind both the "view" (open
     * the Editor) and "update" (save) abilities in DiagramPolicy.
     */
    public function isEditableBy(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        if (DiagramCollaborator::where('diagram_id', $this->id)
            ->where('user_id', $user->id)
            ->where('status', DiagramCollaborator::STATUS_APPROVED)
            ->exists()) {
            return true;
        }

        return $this->workspace_id !== null && ($this->workspace?->hasMember($user) ?? false);
    }

    /**
     * True if $user can see this Diagram exists at all (CONTEXT.md,
     * "Workspace"; issue #28): a Diagram outside any Workspace is visible
     * to everyone, an Administrator sees every Diagram, and a hidden
     * Diagram (inside a Workspace) is visible only to whoever can edit it.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if ($this->workspace_id === null) {
            return true;
        }

        return $this->isEditableBy($user);
    }

    /**
     * Seconds without a heartbeat after which the Editing Lock is considered
     * released due to inactivity.
     */
    public const LOCK_INACTIVITY_TIMEOUT_SECONDS = 90;

    /**
     * Seconds the current lock holder has to respond (heartbeat) to a
     * request before it's automatically transferred to whoever requested it
     * (CONTEXT.md, "Editing Lock").
     */
    public const LOCK_REQUEST_TRANSFER_SECONDS = 30;

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
     * True if the Diagram is locked and the heartbeat (or, absent a
     * heartbeat, the moment the lock was acquired) is still within the
     * inactivity timeout. Keep in sync with the query-level equivalent,
     * scopeLockedNow() below.
     */
    public function hasActiveLock(): bool
    {
        if ($this->locked_by === null) {
            return false;
        }

        $reference = $this->last_heartbeat_at ?? $this->locked_at;

        if ($reference === null) {
            return false;
        }

        return $reference->greaterThan(now()->subSeconds(self::LOCK_INACTIVITY_TIMEOUT_SECONDS));
    }

    public function isLockedByOther(User $user): bool
    {
        return $this->hasActiveLock() && (int) $this->locked_by !== $user->id;
    }

    public function acquireLock(User $user): void
    {
        $this->forceFill([
            'locked_by' => $user->id,
            'locked_at' => now(),
            'last_heartbeat_at' => now(),
        ])->save();
    }

    /**
     * Updates the Lock's heartbeat. Only has an effect if the given User is
     * the current lock holder. Any heartbeat counts as the holder
     * "responding", cancelling a pending transfer request.
     */
    public function heartbeat(User $user): bool
    {
        if ((int) $this->locked_by !== $user->id) {
            return false;
        }

        $this->forceFill([
            'last_heartbeat_at' => now(),
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ])->save();

        return true;
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

        $this->forceFill([
            'locked_by' => null,
            'locked_at' => null,
            'last_heartbeat_at' => null,
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ])->save();

        return true;
    }

    /**
     * True if there's a pending Lock request made by $user.
     */
    public function pendingLockRequestIsFrom(User $user): bool
    {
        return $this->lock_requested_by !== null && (int) $this->lock_requested_by === $user->id;
    }

    /**
     * True if the pending request is already past
     * LOCK_REQUEST_TRANSFER_SECONDS without the holder responding, and is
     * therefore ready to be transferred automatically.
     */
    public function lockRequestTransferIsDue(): bool
    {
        return $this->lock_requested_at !== null
            && $this->lock_requested_at->lessThanOrEqualTo(now()->subSeconds(self::LOCK_REQUEST_TRANSFER_SECONDS));
    }

    /**
     * Registers $user as requesting the Lock, starting the
     * LOCK_REQUEST_TRANSFER_SECONDS window. Doesn't restart the window if
     * there's already a pending request from the same User.
     */
    public function registerLockRequest(User $user): void
    {
        if ($this->pendingLockRequestIsFrom($user)) {
            return;
        }

        $this->forceFill([
            'lock_requested_by' => $user->id,
            'lock_requested_at' => now(),
        ])->save();
    }

    /**
     * Transfers the Lock to $user, ending the pending request.
     */
    public function transferLockTo(User $user): void
    {
        $this->forceFill([
            'locked_by' => $user->id,
            'locked_at' => now(),
            'last_heartbeat_at' => now(),
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ])->save();
    }

    /** @return HasMany<DiagramOldSlug, $this> */
    public function oldSlugs(): HasMany
    {
        return $this->hasMany(DiagramOldSlug::class);
    }

    /** @return HasMany<DiagramCollaborator, $this> */
    public function collaborators(): HasMany
    {
        return $this->hasMany(DiagramCollaborator::class);
    }

    /** @return HasMany<Share, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    /**
     * Generates a unique slug from a free-form name, normalizing it and
     * appending a short alphanumeric suffix on collision with the current
     * slug of any Diagram or with a reserved old slug.
     */
    public static function uniqueSlug(string $name, ?int $ignoreDiagramId = null): string
    {
        $base = Slugger::slugify($name);
        $slug = $base;
        $attempts = 0;
        $maxAttempts = 20;

        while (self::slugIsTaken($slug, $ignoreDiagramId)) {
            if (++$attempts > $maxAttempts) {
                $slug = $base.'-'.Str::lower(Str::random(12));

                break;
            }

            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private static function slugIsTaken(string $slug, ?int $ignoreDiagramId): bool
    {
        $query = static::where('slug', $slug);

        if ($ignoreDiagramId !== null) {
            $query->where('id', '!=', $ignoreDiagramId);
        }

        if ($query->exists()) {
            return true;
        }

        return DiagramOldSlug::where('old_slug', $slug)->exists();
    }

    /**
     * Matches $search, case-insensitively, against title, Slug (issue #38),
     * or the BPMN content's searchable text (issue #41; see
     * BpmnSearchableText). '%' and '_' in the User's input are literal
     * characters here, not LIKE wildcards — escaped before being wrapped in
     * the pattern.
     *
     * On Postgres, title/Slug go through ILIKE (locale-aware casing, so
     * accented letters fold correctly — unlike a hand-rolled LOWER()
     * comparison) and the content clause queries through the GIN expression
     * index the migration builds (to_tsvector/plainto_tsquery), instead of
     * leaving that index unused. Every other driver — in practice only
     * sqlite, which the test suite is forced onto by phpunit.xml — falls
     * back to a portable LOWER()+LIKE match; LOWER() there only folds
     * ASCII case, a known sqlite limitation that never reaches production.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $escaped = strtr($search, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
        $pattern = '%'.$escaped.'%';
        $escapeClause = "ESCAPE '\\'";

        return $query->where(function (Builder $query) use ($search, $escaped, $pattern, $escapeClause) {
            $isPostgres = DB::getDriverName() === 'pgsql';
            $term = $isPostgres ? $pattern : '%'.mb_strtolower($escaped).'%';
            $operator = $isPostgres ? 'ILIKE' : 'LIKE';
            $wrap = $isPostgres ? fn (string $column) => $column : fn (string $column) => "LOWER({$column})";

            foreach (['title', 'slug'] as $column) {
                $query->orWhereRaw("{$wrap($column)} {$operator} ? {$escapeClause}", [$term]);
            }

            if ($isPostgres) {
                $query->orWhereRaw(
                    "to_tsvector('simple', coalesce(searchable_text, '')) @@ plainto_tsquery('simple', ?)",
                    [$search]
                );

                return;
            }

            $query->orWhereRaw("LOWER(coalesce(searchable_text, '')) LIKE ? {$escapeClause}", [$term]);
        });
    }
}
