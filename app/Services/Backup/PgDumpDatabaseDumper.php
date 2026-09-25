<?php

namespace App\Services\Backup;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Dumps the `pgsql` connection with `pg_dump`, in its custom archive format
 * (`-Fc`) so a future restore can `pg_restore` it directly instead of
 * replaying a plain-SQL script.
 */
class PgDumpDatabaseDumper implements DatabaseDumper
{
    public function dump(string $destination): string
    {
        $connection = config('database.connections.pgsql');

        $process = new Process([
            'pg_dump',
            '--host', $connection['host'],
            '--port', (string) $connection['port'],
            '--username', $connection['username'],
            '--format', 'custom',
            '--no-owner',
            '--file', $destination,
            $connection['database'],
        ], env: [
            'PGPASSWORD' => $connection['password'],
        ]);

        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $destination;
    }
}
