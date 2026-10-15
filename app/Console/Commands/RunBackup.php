<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Repositories\BackupRepository;
use Illuminate\Console\Command;
use Throwable;

/**
 * Produces one backup artifact (Diagrams + database, see BackupRepository)
 * and prunes artifacts past the configured retention window. Scheduled
 * periodically from the `scheduler` compose service (see
 * bootstrap/app.php's `withSchedule` and docker-compose.yml) instead of a
 * host cron. When the off-site copy is enabled (config/backup.php,
 * ADR-0026) the artifact is also uploaded and the off-site disk pruned by
 * its own retention.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the Diagrams directory and the database into one artifact, then prune expired artifacts';

    public function handle(BackupRepository $backups): int
    {
        $artifact = $backups->run();
        BackupRun::record(BackupRun::KIND_BACKUP, true, $artifact);
        $this->info("Backup artifact created: {$artifact}");

        $pruned = $backups->prune();

        foreach ($pruned as $file) {
            $this->info("Pruned expired artifact: {$file}");
        }

        return config('backup.offsite.enabled') ? $this->copyOffsite($backups, $artifact) : self::SUCCESS;
    }

    /**
     * A failed upload leaves the local artifact in place and fails the
     * command, so the scheduler output shows it and the Administrators'
     * status page stops reporting a fresh off-site copy.
     */
    private function copyOffsite(BackupRepository $backups, string $artifact): int
    {
        try {
            $backups->upload($artifact);
            $pruned = $backups->pruneOffsite();
        } catch (Throwable $e) {
            BackupRun::record(BackupRun::KIND_OFFSITE, false, $artifact, $e->getMessage());
            $this->error("Off-site copy failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        BackupRun::record(BackupRun::KIND_OFFSITE, true, $artifact);
        $this->info("Backup artifact copied off-site: {$artifact}");

        foreach ($pruned as $file) {
            $this->info("Pruned expired off-site artifact: {$file}");
        }

        return self::SUCCESS;
    }
}
