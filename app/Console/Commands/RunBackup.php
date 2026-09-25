<?php

namespace App\Console\Commands;

use App\Repositories\BackupRepository;
use Illuminate\Console\Command;

/**
 * Produces one backup artifact (Diagrams + database, see BackupRepository)
 * and prunes artifacts past the configured retention window. Scheduled
 * periodically from the `scheduler` compose service (see
 * bootstrap/app.php's `withSchedule` and docker-compose.yml) instead of a
 * host cron.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the Diagrams directory and the database into one artifact, then prune expired artifacts';

    public function handle(BackupRepository $backups): int
    {
        $artifact = $backups->run();
        $this->info("Backup artifact created: {$artifact}");

        $pruned = $backups->prune();

        foreach ($pruned as $file) {
            $this->info("Pruned expired artifact: {$file}");
        }

        return self::SUCCESS;
    }
}
