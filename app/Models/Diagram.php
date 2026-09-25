<?php

namespace App\Models;

use App\Support\Slugger;
use Database\Factories\DiagramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['slug', 'title', 'author_id', 'owner_id', 'disk_path', 'forked_from_id'])]
class Diagram extends Model
{
    /** @use HasFactory<DiagramFactory> */
    use HasFactory;

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

    public function forkedFrom(): BelongsTo
    {
        return $this->belongsTo(Diagram::class, 'forked_from_id');
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

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function lockRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lock_requested_by');
    }

    /**
     * True if the Diagram is locked and the heartbeat (or, absent a
     * heartbeat, the moment the lock was acquired) is still within the
     * inactivity timeout.
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

    public function oldSlugs(): HasMany
    {
        return $this->hasMany(DiagramOldSlug::class);
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(DiagramCollaborator::class);
    }

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

        while (static::slugIsTaken($slug, $ignoreDiagramId)) {
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
}
