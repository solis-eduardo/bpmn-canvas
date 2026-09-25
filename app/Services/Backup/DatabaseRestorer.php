<?php

namespace App\Services\Backup;

/**
 * The boundary between restore orchestration (RestoreRepository) and however
 * the database actually gets restored from disk. Kept as an interface so a test
 * can swap the real `pg_restore` process for a fake at this seam, without a
 * real Postgres server.
 */
interface DatabaseRestorer
{
    /**
     * Restores the database from a dump file at $source (an absolute file path).
     */
    public function restore(string $source): void;
}
