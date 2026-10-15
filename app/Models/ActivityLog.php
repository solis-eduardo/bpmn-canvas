<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
     * The entry's before/after as displayable rows, one per changed field:
     * the translated field name and the formatted value on each side (null
     * when that side did not have the field). Dates are shown in the
     * viewer's time zone.
     *
     * @return list<array{field: string, before: ?string, after: ?string}>
     */
    public function changes(User $viewer): array
    {
        $before = $this->before ?? [];
        $after = $this->after ?? [];

        return array_map(fn (string $key) => [
            'field' => __('messages.activity_field_'.$key),
            'before' => array_key_exists($key, $before) ? $this->display($viewer, $key, $before[$key]) : null,
            'after' => array_key_exists($key, $after) ? $this->display($viewer, $key, $after[$key]) : null,
        ], array_values(array_unique([...array_keys($before), ...array_keys($after)])));
    }

    private function display(User $viewer, string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? __('messages.yes') : __('messages.no');
        }

        if ($value !== null && str_ends_with($key, '_at')) {
            return $viewer->inDisplayTimezone(Carbon::parse($value))->isoFormat('L LT');
        }

        return $value === null || $value === '' ? '—' : (string) $value;
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
