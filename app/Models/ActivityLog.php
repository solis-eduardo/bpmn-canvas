<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One entry of the append-only activity log (CONTEXT.md, "Activity Log";
 * ADR-0025). An entry is written once by ActivityLogger and never changed or
 * deleted through the model; the only way entries leave is the retention
 * prune ({@see prune()}).
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Every action ActivityLogger records, as shown in the filters. Each has a
     * label under `messages.activity_action_<action with dots as underscores>`.
     *
     * @var list<string>
     */
    public const ACTIONS = [
        'diagram.owner_transferred',
        'workspace.owner_transferred',
        'collaborator.granted',
        'collaborator.role_changed',
        'collaborator.removed',
        'collaborator.request_declined',
        'share.created',
        'share.revoked',
        'lock.force_released',
        'workspace.member_added',
        'workspace.member_removed',
        'administrator.granted',
        'administrator.revoked',
    ];

    /** @var list<string> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity log entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Activity log entries are append-only.'));
    }

    /**
     * Entries about one Diagram, newest first.
     *
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    public function scopeForDiagram(Builder $query, Diagram $diagram): Builder
    {
        return $query->where('diagram_id', $diagram->id);
    }

    /**
     * The retention prune: removes entries older than $days at the query
     * level, deliberately bypassing the model's append-only guard.
     */
    public static function prune(int $days): int
    {
        return static::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
