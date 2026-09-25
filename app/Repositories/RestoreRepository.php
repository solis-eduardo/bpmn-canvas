<?php

namespace App\Repositories;

use App\Services\Backup\DatabaseRestorer;
use App\Support\ProcessRunner;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Restores backup artifacts. Validates the artifact before touching anything,
 * then restores both the diagrams directory (with its .git history) and the
 * database dump.
 *
 * Expected artifact structure (see BackupRepository):
 *   - `diagrams/`     a full copy of the Diagrams disk
 *   - `database.dump` a `pg_dump --format=custom` dump of the database
 *   - `manifest.json` metadata for validation
 */
class RestoreRepository
{
    private const MANIFEST_FORMAT = 1;

    public function __construct(private DatabaseRestorer $databaseRestorer) {}

    /**
     * Validates and restores a backup artifact from the `backups` disk.
     * Throws RuntimeException if validation fails.
     *
     * @throws RuntimeException
     */
    public function restore(string $artifactName): void
    {
        $disk = Storage::disk('backups');

        if (! $disk->exists($artifactName)) {
            throw new RuntimeException("Backup artifact not found: {$artifactName}");
        }

        if (! str_ends_with($artifactName, '.tar.gz')) {
            throw new RuntimeException("Invalid artifact name: {$artifactName}");
        }

        $artifactPath = $disk->path($artifactName);
        $extractDir = $disk->path('.extracting-'.(string) Str::uuid());

        try {
            $this->makeDirectory($extractDir);
            $this->extract($artifactPath, $extractDir);

            // Validate manifest before touching anything
            $this->validateManifest($extractDir.'/manifest.json');

            // Restore diagrams directory
            $this->restoreDiagrams($extractDir.'/diagrams');

            // Restore database
            $this->databaseRestorer->restore($extractDir.'/database.dump');
        } finally {
            $this->removeDirectory($extractDir);
        }
    }

    /**
     * Extracts the tar.gz artifact to a temporary directory.
     */
    private function extract(string $artifactPath, string $destination): void
    {
        $process = ProcessRunner::run([
            'tar', '-xzf', $artifactPath, '-C', $destination,
        ]);
    }

    /**
     * Validates the manifest.json exists and has the correct format version.
     *
     * @throws RuntimeException
     */
    private function validateManifest(string $manifestPath): void
    {
        if (! is_file($manifestPath)) {
            throw new RuntimeException('Backup artifact is invalid: missing manifest.json');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (! isset($manifest['format'])) {
            throw new RuntimeException('Backup artifact is invalid: manifest has no format');
        }

        if ($manifest['format'] !== self::MANIFEST_FORMAT) {
            throw new RuntimeException(sprintf(
                'Backup artifact format version %d is not supported (expected %d)',
                $manifest['format'],
                self::MANIFEST_FORMAT,
            ));
        }
    }

    /**
     * Restores the diagrams directory to the `diagrams` disk.
     * Uses `cp -a` to preserve all files including hidden .git directories.
     */
    private function restoreDiagrams(string $sourceDir): void
    {
        $diagramsDisk = Storage::disk('diagrams');
        $diagramsPath = $diagramsDisk->path('');

        // Clear existing diagrams
        if (is_dir($diagramsPath)) {
            $this->removeDirectory($diagramsPath);
        }

        $this->makeDirectory($diagramsPath);

        if (! is_dir($sourceDir)) {
            // Backup had no diagrams, that's fine
            return;
        }

        // Copy with hidden files preserved (including .git)
        ProcessRunner::run(['cp', '-a', rtrim($sourceDir, '/').'/.', $diagramsPath]);
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create directory: {$path}");
        }
    }

    private function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            ProcessRunner::run(['rm', '-rf', $path]);
        }
    }
}
