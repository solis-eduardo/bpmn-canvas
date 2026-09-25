<?php

namespace App\Services\Backup;

use App\Support\ProcessRunner;

/**
 * Loads a `pg_dump --format=custom` dump (see PgDumpDatabaseDumper) back
 * into the `pgsql` connection with `pg_restore --clean --if-exists`, so
 * existing objects are dropped and recreated rather than erroring out
 * because they already exist. `--exit-on-error` overrides pg_restore's
 * default of logging and continuing past a failed statement, so a partial
 * or incompatible dump fails the restore instead of silently leaving the
 * database in a half-restored state.
 */
class PgRestoreDatabaseRestorer implements DatabaseRestorer
{
    public function restore(string $source): void
    {
        $connection = config('database.connections.pgsql');

        ProcessRunner::run([
            'pg_restore',
            '--host', $connection['host'],
            '--port', (string) $connection['port'],
            '--username', $connection['username'],
            '--clean',
            '--if-exists',
            '--no-owner',
            '--exit-on-error',
            '--dbname', $connection['database'],
            $source,
        ], env: [
            'PGPASSWORD' => $connection['password'],
        ]);
    }
}
