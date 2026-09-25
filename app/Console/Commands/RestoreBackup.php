<?php

namespace App\Console\Commands;

use App\Repositories\RestoreRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Restores the database and Diagrams directory from a backup artifact
 * produced by `backup:run` (see BackupRepository/RestoreRepository),
 * OVERWRITING whatever is currently there. Run by an operator, never
 * scheduled — unlike `backup:run` this is deliberately not wired into
 * `bootstrap/app.php`'s `withSchedule`.
 */
class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {artifact : Filename of the backup artifact on the "backups" disk}
        {--force : Skip the confirmation prompt (for non-interactive use)}';

    protected $description = 'Restore the database and Diagrams directory from a backup artifact, OVERWRITING current state';

    public function handle(RestoreRepository $restores): int
    {
        $artifact = $this->argument('artifact');

        if (! Storage::disk('backups')->exists($artifact)) {
            $this->error("Backup artifact not found: {$artifact}");

            return self::FAILURE;
        }

        $confirmed = $this->option('force') || $this->confirm(
            "This will OVERWRITE the current database and Diagrams directory with the contents of \"{$artifact}\". ".
            'This cannot be undone. Continue?'
        );

        if (! $confirmed) {
            $this->warn('Restore cancelled.');

            return self::FAILURE;
        }

        $this->info("Restoring from backup artifact: {$artifact}");

        $restores->restore($artifact);

        $this->info('Restore complete.');

        return self::SUCCESS;
    }
}
