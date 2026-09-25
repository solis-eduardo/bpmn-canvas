<?php

namespace App\Services\Backup;

use App\Support\ProcessRunner;

/**
 * Loads a `pg_dump --format=custom` dump (see PgDumpDatabaseDumper) back
 * into the `pgsql` connection with `pg_restore --clean --if-exists`, so
 * existing objects are dropped and recreated rather than erroring out
 * because they already exist.
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
            '--dbname', $connection['database'],
            '--clean',
            '--if-exists',
            '--no-owner',
            $source,
        ], env: [
            'PGPASSWORD' => $connection['password'],
        ]);
    }
}
