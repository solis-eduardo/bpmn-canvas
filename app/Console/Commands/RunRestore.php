<?php

namespace App\Console\Commands;

use App\Repositories\RestoreRepository;
use Illuminate\Console\Command;

/**
 * Restores a backup artifact (Diagrams + database, see RestoreRepository)
 * after explicit operator confirmation.
 *
 * Usage:
 *   php artisan restore:run <artifact-name>
 *
 * Requires the operator to answer "yes" to an explicit confirmation prompt
 * before proceeding, since restore is destructive: it clears all diagrams
 * and replays database schema/data from the backup.
 */
class RunRestore extends Command
{
    protected $signature = 'restore:run {artifact : The backup artifact filename to restore}';

    protected $description = 'Restore a backup artifact (diagrams and database) with explicit confirmation';

    public function handle(RestoreRepository $restores): int
    {
        $artifactName = $this->argument('artifact');

        if (! $this->confirm('This will overwrite all diagrams and the database. Continue?')) {
            $this->warn('Restore cancelled.');

            return self::FAILURE;
        }

        try {
            $restores->restore($artifactName);
            $this->info("Restore completed successfully from artifact: {$artifactName}");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error("Restore failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
