<?php

namespace App\Services\Backup;

use App\Support\ProcessRunner;

/**
 * Restores the `pgsql` connection from a `pg_dump` custom archive format
 * dump (created by PgDumpDatabaseDumper) using `pg_restore`.
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
