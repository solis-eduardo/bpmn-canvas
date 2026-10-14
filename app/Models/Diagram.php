<?php

namespace App\Models;

use App\Models\Concerns\FiltersDiagramList;
use App\Models\Concerns\HoldsALock;
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
    use HoldsALock;

    /**
     * Answers of isEditableBy() for this instance, keyed by User id. Cleared
     * whenever the instance is saved or re-read, so a check made after a
     * change to the Diagram or to its access rows (through the Diagram's
     * own instance) is never answered from before that change.
     *
     * @var array<int, bool>
     */
    private array $editableByCache = [];

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
     * True if $user may invite, approve and remove Collaborators: whoever
     * administers the Diagram or holds the Diagram Manager role
     * (ADR-0017).
     */
    public function canManageCollaborators(User $user): bool
    {
        return $this->isManagedBy($user) || $this->hasManagerRole($user);
    }

    /**
     * True if $user is an approved Diagram Manager of this Diagram — a
     * Collaborator the Diagram Owner delegated Collaborator management to
     * (CONTEXT.md, "Diagram Manager"; ADR-0017).
     */
    public function hasManagerRole(User $user): bool
    {
        return $this->collaborators()
            ->where('user_id', $user->id)
            ->where('status', Collaborator::STATUS_APPROVED)
            ->where('role', Collaborator::ROLE_MANAGER)
            ->exists();
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
     * True if $user is a direct, approved Collaborator of this Diagram
     * (not merely a Workspace Member).
     */
    public function hasApprovedCollaborator(User $user): bool
    {
        return $this->collaborators()
            ->where('user_id', $user->id)
            ->where('status', Collaborator::STATUS_APPROVED)
            ->exists();
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

        return $this->editableByCache[$user->id] ??= static::query()
            ->whereKey($this->getKey())
            ->editableBy($user)
            ->exists();
    }

    /**
     * Re-reads the Diagram from the database, so the memoized answers of
     * isEditableBy() are dropped along with the stale attributes.
     */
    public function refresh(): static
    {
        $this->editableByCache = [];

        return parent::refresh();
    }

    protected static function booted(): void
    {
        static::saved(function (Diagram $diagram): void {
            $diagram->editableByCache = [];
        });
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
        return $query->where(function (Builder $query) use ($search) {
            self::whereLikeMatch($query, 'title', $search, 'or');
            self::whereLikeMatch($query, 'slug', $search, 'or');

            if (DB::getDriverName() === 'pgsql') {
                $query->orWhereRaw(
                    "to_tsvector('simple', coalesce(searchable_text, '')) @@ plainto_tsquery('simple', ?)",
                    [$search]
                );

                return;
            }

            $query->orWhereRaw("LOWER(coalesce(searchable_text, '')) LIKE ? ESCAPE '\\'", [self::likeTerm($search)]);
        });
    }

    /**
     * Matches $search against the title alone, with the same escaping and
     * casing rules as scopeSearch(). Used by discovery, which only matches
     * by title (ADR-0013).
     */
    public function scopeTitleMatches(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $query) => self::whereLikeMatch($query, 'title', $search, 'and'));
    }

    /**
     * Adds a LIKE clause on $column: ILIKE on Postgres, LOWER()+LIKE elsewhere.
     */
    private static function whereLikeMatch(Builder $query, string $column, string $search, string $boolean): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';
        $expression = $isPostgres ? $column : "LOWER({$column})";
        $operator = $isPostgres ? 'ILIKE' : 'LIKE';

        $query->whereRaw("{$expression} {$operator} ? ESCAPE '\\'", [self::likeTerm($search)], $boolean);
    }

    /**
     * The LIKE pattern for $search: '%' and '_' (and the escape character
     * itself) escaped so they match literally, wrapped in '%', and lowercased
     * on the non-Postgres path, where LIKE is case-sensitive or ASCII-only.
     */
    private static function likeTerm(string $search): string
    {
        $escaped = strtr($search, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);

        return '%'.(DB::getDriverName() === 'pgsql' ? $escaped : mb_strtolower($escaped)).'%';
    }
}
