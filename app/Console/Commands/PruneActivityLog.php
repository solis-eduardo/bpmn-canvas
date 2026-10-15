<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Applies the activity log's retention (config/activity.php): the only way
 * entries ever leave the append-only log. Scheduled daily from
 * bootstrap/app.php.
 */
class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune';

    protected $description = 'Delete activity log entries older than the configured retention';

    public function handle(): int
    {
        $days = (int) config('activity.retention_days');

        if ($days <= 0) {
            $this->info('Activity log retention is unlimited; nothing to prune.');

            return self::SUCCESS;
        }

        $this->info('Pruned '.ActivityLog::prune($days).' activity log entries older than '.$days.' days.');

        return self::SUCCESS;
    }
}
