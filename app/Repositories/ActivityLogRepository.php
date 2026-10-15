<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\Diagram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Reads of the activity log, newest first. Writing is ActivityLogger's job.
 */
class ActivityLogRepository
{
    /**
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function search(ActivityLogFilters $filters, ?Diagram $diagram = null): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->when($diagram, fn ($query) => $query->forDiagram($diagram))
            ->when($filters->action, fn ($query, $action) => $query->where('action', $action))
            ->when($filters->actor !== '', fn ($query) => $this->whereContains($query, 'actor_name', $filters->actor))
            ->when($filters->diagram !== '', fn ($query) => $this->whereContains($query, 'diagram_title', $filters->diagram))
            ->when($filters->from, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters->to, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(config('activity.per_page'));
    }

    /**
     * Case-insensitive substring match with '%' and '_' taken literally:
     * ILIKE on Postgres, whose LIKE is case-sensitive; sqlite's LIKE already
     * ignores ASCII case.
     *
     * @param  Builder<ActivityLog>  $query
     */
    private function whereContains(Builder $query, string $column, string $term): void
    {
        $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $pattern = '%'.strtr($term, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';

        $query->whereRaw("{$column} {$operator} ? ESCAPE '\\'", [$pattern]);
    }
}
