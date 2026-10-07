<?php

namespace App\Console\Commands;

use App\Services\LockExpiryService;
use Illuminate\Console\Command;

/**
 * Releases Locks that went without a Heartbeat for the configured TTL
 * (ADR-0003), saving the holder's unsaved content as a provisional Version.
 * Scheduled every minute from the `scheduler` compose service (see
 * bootstrap/app.php's `withSchedule`).
 */
class ExpireLocks extends Command
{
    protected $signature = 'locks:expire';

    protected $description = 'Release Locks without a Heartbeat for the configured TTL, saving unsaved content as a provisional Version';

    public function handle(LockExpiryService $expiry): int
    {
        $this->info("Released {$expiry->releaseExpired()} expired Lock(s).");

        return self::SUCCESS;
    }
}
