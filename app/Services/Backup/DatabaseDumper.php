<?php

namespace App\Services\Backup;

/**
 * The boundary between backup orchestration (BackupRepository) and however
 * the database actually gets dumped to disk. Kept as an interface so a test
 * can swap the real `pg_dump` process for a fake at this seam, without a
 * real Postgres server (BackupRepository's console-seam test does exactly
 * this).
 */
interface DatabaseDumper
{
    /**
     * Writes a dump of the application database to $destination (an
     * absolute file path). Returns $destination for convenience.
     */
    public function dump(string $destination): string;
}
