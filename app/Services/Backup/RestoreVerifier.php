<?php

namespace App\Services\Backup;

/**
 * The boundary between backup verification (BackupVerificationRepository)
 * and a throwaway database: loads a dump into it, reports what came back,
 * and discards it. An interface so a test can swap the real Postgres work
 * for a fake without a server.
 */
interface RestoreVerifier
{
    /**
     * Restores the dump at $dump (a DatabaseDumper product) into a scratch
     * database, never the application's own, and returns the row count of
     * every table in it. The scratch database is gone afterwards, whether or
     * not the restore succeeded.
     *
     * @return array<string, int>
     */
    public function rowCounts(string $dump): array;
}
