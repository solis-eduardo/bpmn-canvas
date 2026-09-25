<?php

namespace App\Services\Backup;

/**
 * The restore-side counterpart of {@see DatabaseDumper}: the boundary
 * between restore orchestration (RestoreRepository) and however the
 * database actually gets loaded back from a dump. Kept as an interface so a
 * test can swap the real `pg_restore` process for a fake at this seam,
 * without a real Postgres server (RestoreRepository's console-seam test does
 * exactly this).
 */
interface DatabaseRestorer
{
    /**
     * Loads the dump at $source (an absolute file path, produced by a
     * DatabaseDumper) back into the application database, replacing its
     * current contents.
     */
    public function restore(string $source): void;
}
