<?php

namespace App\Repositories;

use App\Services\Backup\DatabaseRestorer;
use App\Support\AccessMemo;
use App\Support\BackupManifest;
use App\Support\ProcessRunner;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Restores one backup artifact produced by {@see BackupRepository} (same
 * `.tar.gz` layout: `diagrams/`, `database.dump`, `manifest.json`). No
 * archive-format, staging-path, or shell-out terms should leak out of this
 * class: callers only ever see "restore".
 *
 * Nothing existing is touched until the artifact has been extracted and its
 * manifest validated — a corrupt or unsupported artifact fails before the
 * database or Diagrams directory are ever overwritten.
 */
class RestoreRepository
{
    public function __construct(private DatabaseRestorer $databaseRestorer, private AccessMemo $access) {}

    /**
     * Restores the database and Diagrams directory from $artifact (a
     * filename on the `backups` disk), overwriting their current contents.
     */
    public function restore(string $artifact): void
    {
        $disk = Storage::disk('backups');

        if (! $disk->exists($artifact)) {
            throw new RuntimeException("Backup artifact not found: {$artifact}");
        }

        if (! str_ends_with($artifact, '.tar.gz')) {
            throw new RuntimeException("Invalid artifact name: {$artifact}");
        }

        $stagingDir = $disk->path('.restore-'.(string) Str::uuid());

        try {
            $this->makeDirectory($stagingDir);
            $this->extract($disk->path($artifact), $stagingDir);
            BackupManifest::read($stagingDir.'/manifest.json');

            // Only once the artifact has been extracted and validated does
            // anything existing get touched.
            $this->databaseRestorer->restore($stagingDir.'/database.dump');

            $this->replaceDiagrams($stagingDir.'/diagrams');
        } finally {
            // The restore swaps every access row without a model event, and a
            // restore that fails midway may have swapped some of them.
            $this->access->forget();
            $this->removeDirectory($stagingDir);
        }
    }

    private function extract(string $archivePath, string $destination): void
    {
        ProcessRunner::run(['tar', '-xzf', $archivePath, '-C', $destination]);
    }

    private function replaceDiagrams(string $source): void
    {
        if (! is_dir($source)) {
            return;
        }

        $diagramsRoot = rtrim(Storage::disk('diagrams')->path(''), '/');
        $this->makeDirectory($diagramsRoot);

        // Clears the existing Diagrams directory before copying the
        // artifact's copy in, so Diagrams removed since the backup don't
        // linger after a restore. Uses `find -delete` rather than removing
        // and recreating the directory itself, so the mount point (a Docker
        // volume in production) isn't disturbed.
        ProcessRunner::run(['find', $diagramsRoot, '-mindepth', '1', '-delete']);
        ProcessRunner::run(['cp', '-a', rtrim($source, '/').'/.', $diagramsRoot]);
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create restore staging directory: {$path}");
        }
    }

    private function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            ProcessRunner::run(['rm', '-rf', $path]);
        }
    }
}
