<?php

namespace App\Services\Backup;

use App\Support\ProcessRunner;
use Illuminate\Support\Str;

/**
 * Restores the dump into a freshly created, uniquely named Postgres database
 * with `pg_restore`, counts its tables' rows with `psql`, then drops it.
 * Needs the connecting role to be allowed to CREATE DATABASE.
 */
class PgRestoreVerifier implements RestoreVerifier
{
    private const COUNT_QUERY = <<<'SQL'
        select table_name || '|' || (xpath('/row/c/text()',
            query_to_xml(format('select count(*) as c from %I.%I', table_schema, table_name), false, true, '')))[1]::text
        from information_schema.tables
        where table_schema = 'public' and table_type = 'BASE TABLE'
        SQL;

    public function rowCounts(string $dump): array
    {
        $connection = config('database.connections.pgsql');
        $scratch = 'bpmn_verify_'.str_replace('-', '', (string) Str::uuid());
        $env = ['PGPASSWORD' => $connection['password']];
        $server = ['--host', $connection['host'], '--port', (string) $connection['port'], '--username', $connection['username']];

        ProcessRunner::run(['createdb', ...$server, $scratch], env: $env);

        try {
            ProcessRunner::run(['pg_restore', ...$server, '--no-owner', '--exit-on-error', '--dbname', $scratch, $dump], env: $env);
            $output = ProcessRunner::run(['psql', ...$server, '--no-psqlrc', '--tuples-only', '--no-align', '--dbname', $scratch, '--command', self::COUNT_QUERY], env: $env)->getOutput();
        } finally {
            ProcessRunner::run(['dropdb', ...$server, '--if-exists', $scratch], env: $env);
        }

        $counts = [];
        foreach (array_filter(array_map('trim', explode("\n", $output))) as $line) {
            [$table, $count] = explode('|', $line, 2);
            $counts[$table] = (int) $count;
        }

        return $counts;
    }
}
