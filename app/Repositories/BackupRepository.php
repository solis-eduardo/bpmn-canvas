<?php

namespace App\Repositories;

use App\Services\Backup\DatabaseDumper;
use App\Support\ProcessRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Produces and prunes backup artifacts. No archive-format, staging-path, or
 * shell-out terms should leak out of this class: callers only ever see
 * "run" (produce one artifact) and "prune" (delete expired ones).
 *
 * An artifact is a single `.tar.gz` file, named
 * `backup-<timestamp>-<random>.tar.gz` (the random suffix only disambiguates
 * two runs started in the same second; it carries no meaning of its own),
 * containing:
 *   - `diagrams/`     a full copy of the Diagrams disk (every Diagram's
 *                      `.bpmn` file and its internal Version repository,
 *                      see VersionRepository / ADR-0004).
 *   - `database.dump` a `pg_dump --format=custom` dump of the database.
 *   - `manifest.json` metadata (format version, creation time) for a future
 *                      restore command to validate before touching anything.
 */
class BackupRepository
{
    private const MANIFEST_FORMAT = 1;

    private const FILENAME_FORMAT = 'Y-m-d_His';

    private const FILENAME_PATTERN = '/^backup-(\d{4}-\d{2}-\d{2}_\d{6})-[0-9a-f]{8}\.tar\.gz$/';

    public function __construct(private DatabaseDumper $databaseDumper) {}

    /**
     * Builds one backup artifact on the `backups` disk and returns its
     * filename.
     */
    public function run(): string
    {
        $disk = Storage::disk('backups');
        $disk->makeDirectory('');

        // A run killed mid-way (OOM, `docker compose down`, a crash) never
        // reaches the `finally` below, leaving its `.staging-*` directory
        // behind; `prune()` only ever looks at finished `backup-*.tar.gz`
        // artifacts, so nothing else would reclaim it. Sweep leftovers from
        // any such run before starting a new one.
        $this->removeStaleStagingDirectories($disk->path(''));

        $stagingDir = $disk->path('.staging-'.(string) Str::uuid());
        $filename = 'backup-'.Carbon::now()->format(self::FILENAME_FORMAT).'-'.bin2hex(random_bytes(4)).'.tar.gz';
        $destinationPath = $disk->path($filename);
        $partialPath = $destinationPath.'.part';

        try {
            $this->makeDirectory($stagingDir);
            $this->makeDirectory($stagingDir.'/diagrams');

            $this->copyDiagrams($stagingDir.'/diagrams');
            $this->databaseDumper->dump($stagingDir.'/database.dump');
            $this->writeManifest($stagingDir.'/manifest.json');

            // Archived under a `.part` name and renamed into place only once
            // complete, so nothing ever sees `backup-*.tar.gz` under its
            // real name while still mid-write — a listing, an offsite sync,
            // or a future restore command always sees either a finished
            // artifact or none at all.
            $this->archive($stagingDir, $partialPath);
            rename($partialPath, $destinationPath);
        } finally {
            $this->removeDirectory($stagingDir);
            if (is_file($partialPath)) {
                unlink($partialPath);
            }
        }

        return $filename;
    }

    /**
     * Deletes artifacts older than the configured retention window. Returns
     * the filenames that were removed.
     *
     * Age is read from the artifact's own filename (the run's timestamp),
     * not the file's filesystem mtime: the mtime is set by the host clock,
     * which a test can't travel, while `Carbon::now()` (and so the
     * filename) follows `Carbon::setTestNow()`.
     *
     * @return array<int, string>
     */
    public function prune(): array
    {
        $disk = Storage::disk('backups');
        $cutoff = Carbon::now()->subDays((int) config('backup.retention_days'));

        $expired = [];

        foreach ($disk->files() as $file) {
            if (! preg_match(self::FILENAME_PATTERN, basename($file), $matches)) {
                continue;
            }

            $createdAt = Carbon::createFromFormat(self::FILENAME_FORMAT, $matches[1]);

            if ($createdAt->lessThan($cutoff)) {
                $expired[] = $file;
            }
        }

        foreach ($expired as $file) {
            $disk->delete($file);
        }

        return $expired;
    }

    private function copyDiagrams(string $destination): void
    {
        $diagramsRoot = Storage::disk('diagrams')->path('');

        if (! is_dir($diagramsRoot)) {
            return;
        }

        // Shells out to `cp -a` (rather than Storage::copyDirectory, or a
        // PHP-level recursive copy) so hidden files are preserved verbatim
        // — in particular each Diagram's `.git` Version repository (see
        // VersionRepository / ADR-0004), which a backup that drops history
        // would defeat the point of.
        ProcessRunner::run(['cp', '-a', rtrim($diagramsRoot, '/').'/.', $destination]);
    }

    private function archive(string $stagingDir, string $destinationPath): void
    {
        ProcessRunner::run(['tar', '-czf', $destinationPath, '-C', $stagingDir, '.']);
    }

    private function writeManifest(string $destination): void
    {
        file_put_contents($destination, json_encode([
            'format' => self::MANIFEST_FORMAT,
            'created_at' => Carbon::now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function removeStaleStagingDirectories(string $backupsRoot): void
    {
        $root = rtrim($backupsRoot, '/');

        foreach (glob($root.'/.staging-*', GLOB_ONLYDIR) ?: [] as $stale) {
            $this->removeDirectory($stale);
        }

        foreach (glob($root.'/*.tar.gz.part') ?: [] as $stale) {
            unlink($stale);
        }
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException("Could not create backup staging directory: {$path}");
        }
    }

    private function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            ProcessRunner::run(['rm', '-rf', $path]);
        }
    }
}
