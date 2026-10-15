<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The `manifest.json` that rides along in every backup artifact (ADR-0008):
 * written by BackupRepository, validated by restore and verification before
 * either touches anything else in the artifact.
 */
class BackupManifest
{
    public const FORMAT = 1;

    /**
     * Tables whose rows churn on their own (sessions expire, cache and queue
     * entries are consumed), so a row count taken before the dump says
     * nothing about the restored copy.
     */
    private const TRANSIENT_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /**
     * Writes the manifest, recording how many rows each durable table holds
     * so a later verification can tell an empty restore from a real one.
     */
    public static function write(string $destination): void
    {
        file_put_contents($destination, json_encode([
            'format' => self::FORMAT,
            'created_at' => Carbon::now()->toIso8601String(),
            'row_counts' => self::rowCounts(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Backup artifact is missing manifest.json.');
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Backup artifact has an unsupported manifest format.');
        }

        return $manifest;
    }

    /**
     * @return array<string, int>
     */
    private static function rowCounts(): array
    {
        $counts = [];

        foreach (Schema::getTables() as $table) {
            if (! in_array($table['name'], self::TRANSIENT_TABLES, true)) {
                $counts[$table['name']] = DB::table($table['name'])->count();
            }
        }

        return $counts;
    }
}
