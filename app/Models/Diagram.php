<?php

namespace App\Models;

use App\Models\Concerns\FiltersDiagramList;
use App\Rules\BpmnXml;
use Database\Factories\DiagramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Tags\HasTags;

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
<bpmn:definitions xmlns:bpmn="%2$s"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="%1$s" />
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

        return sprintf(self::EMPTY_BPMN_TEMPLATE, $label, BpmnXml::BPMN_MODEL_NAMESPACE);
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
     * True if $user may administer this Diagram (Collaborators, ownership
     * transfer): its Diagram Owner or an Administrator.
     */
    public function isManagedBy(User $user): bool
    {
        return $this->isOwnedBy($user) || $user->is_admin;
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
        if (! $this->exists) {
            return false;
        }

        return static::query()->whereKey($this->getKey())->editableBy($user)->exists();
    }

    /**
     * True if $user can open this Diagram: an editor, or an Administrator
     * who "sees and opens" every Diagram without edit rights.
     */
    public function isOpenableBy(User $user): bool
    {
        return $this->isEditableBy($user) || $user->is_admin;
    }

    /**
     * Restricts a query to Diagrams a User can open as their Diagram Owner,
     * an approved Collaborator, or a Workspace Member.
     */
    public function scopeEditableBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('owner_id', $user->id)
            ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators
                ->where('user_id', $user->id)
                ->where('status', Collaborator::STATUS_APPROVED))
            ->orWhereHas('workspace.members', fn (Builder $members) => $members->where('user_id', $user->id)));
    }

    /**
     * Restricts a query to Diagrams $user can open: all of them for an
     * Administrator, otherwise only those they can edit.
     */
    public function scopeOpenableBy(Builder $query, User $user): Builder
    {
        return $user->is_admin ? $query : $this->scopeEditableBy($query, $user);
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
     * True while a User holds the Diagram's Lock. A Lock is released
     * explicitly or, once it goes without a Heartbeat for the configured TTL,
     * by `locks:expire` (ADR-0003) — until then it counts as held.
     */
    public function hasActiveLock(): bool
    {
        return $this->locked_by !== null;
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
            'lock_draft' => null,
        ])->save();
    }

    /**
     * Updates the Lock's heartbeat. Only has an effect if the given User is
     * the current lock holder. Any heartbeat counts as the holder
     * "responding", cancelling a pending transfer request.
     */
    public function heartbeat(User $user, ?string $draft = null): bool
    {
        if ((int) $this->locked_by !== $user->id) {
            return false;
        }

        $this->forceFill([
            'last_heartbeat_at' => now(),
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ]);

        if ($draft !== null) {
            $this->lock_draft = $draft;
        }

        $this->save();

        return true;
    }

    /**
     * Drops the holder's unsaved-content draft once it has been saved for
     * real. A plain query update so the Diagram's "last modified" stays put.
     */
    public function clearLockDraft(): void
    {
        static::query()->whereKey($this->getKey())->update(['lock_draft' => null]);
        $this->setAttribute('lock_draft', null);
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
            'lock_draft' => null,
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ])->save();

        return true;
    }

    /**
     * Hands the Lock straight to $newHolder in a single write, dropping any
     * pending request (a Transfer answers it, or pre-empts it).
     */
    public function transferLockTo(User $newHolder): void
    {
        $this->forceFill([
            'locked_by' => $newHolder->id,
            'locked_at' => now(),
            'last_heartbeat_at' => now(),
            'lock_draft' => null,
            'lock_requested_by' => null,
            'lock_requested_at' => null,
        ])->save();
    }

    /**
     * True if there's a pending Lock request made by $user.
     */
    public function pendingLockRequestIsFrom(User $user): bool
    {
        return $this->lock_requested_by !== null && (int) $this->lock_requested_by === $user->id;
    }

    /**
     * Registers $user as requesting the Lock. Doesn't restart the timestamp
     * if there's already a pending request from the same User.
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

    /** @return HasMany<DiagramOldSlug, $this> */
    public function oldSlugs(): HasMany
    {
        return $this->hasMany(DiagramOldSlug::class);
    }

    /** @return HasMany<Collaborator, $this> */
    public function collaborators(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    /** @return HasMany<Share, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
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
