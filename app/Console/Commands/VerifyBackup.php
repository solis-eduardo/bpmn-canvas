<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\BackupVerificationFailed;
use App\Repositories\BackupVerificationRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Restores the latest backup artifact into a throwaway database and
 * directory and checks it (ADR-0026). Scheduled after `backup:run`; on
 * failure it logs, records the failure and notifies every Administrator.
 */
class VerifyBackup extends Command
{
    protected $signature = 'backup:verify';

    protected $description = 'Restore the latest backup artifact into a throwaway database and directory and check its integrity';

    public function handle(BackupVerificationRepository $verification): int
    {
        try {
            $artifact = $verification->verifyLatest();
        } catch (Throwable $e) {
            BackupRun::record(BackupRun::KIND_VERIFICATION, false, message: $e->getMessage());
            Log::error("Backup verification failed: {$e->getMessage()}");
            $this->error("Backup verification failed: {$e->getMessage()}");

            User::where('is_admin', true)->each(fn (User $admin) => $admin->notify(new BackupVerificationFailed($e->getMessage())));

            return self::FAILURE;
        }

        BackupRun::record(BackupRun::KIND_VERIFICATION, true, $artifact);
        $this->info("Backup artifact verified: {$artifact}");

        return self::SUCCESS;
    }
}
