<?php

namespace App\Repositories;

use App\Services\Backup\RestoreVerifier;
use App\Support\BackupManifest;
use App\Support\ProcessRunner;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Proves the latest backup artifact can actually be restored (ADR-0026): the
 * database dump into a throwaway database, the Diagrams into a throwaway
 * directory. Nothing in the application's own database or Diagrams disk is
 * touched, unlike {@see RestoreRepository}.
 */
class BackupVerificationRepository
{
    public function __construct(private BackupRepository $backups, private RestoreVerifier $verifier) {}

    /**
     * Verifies the newest artifact and returns its filename.
     *
     * @throws RuntimeException describing the first problem found
     */
    public function verifyLatest(): string
    {
        $artifact = $this->backups->latest();

        if ($artifact === null) {
            throw new RuntimeException('There is no backup artifact to verify.');
        }

        $disk = Storage::disk('backups');
        $stagingDir = $disk->path('.verify-'.(string) Str::uuid());

        try {
            mkdir($stagingDir, 0755, true);
            ProcessRunner::run(['tar', '-xzf', $disk->path($artifact), '-C', $stagingDir]);

            $manifest = BackupManifest::read($stagingDir.'/manifest.json');
            $this->verifyDatabase($stagingDir.'/database.dump', $manifest['row_counts'] ?? []);
            $this->verifyRepositories($stagingDir.'/diagrams');
        } finally {
            if (is_dir($stagingDir)) {
                ProcessRunner::run(['rm', '-rf', $stagingDir]);
            }
        }

        return $artifact;
    }

    /**
     * @param  array<string, int>  $expected  row counts recorded when the artifact was made
     */
    private function verifyDatabase(string $dump, array $expected): void
    {
        if (! is_file($dump)) {
            throw new RuntimeException('Backup artifact is missing database.dump.');
        }

        $restored = $this->verifier->rowCounts($dump);

        foreach ($expected as $table => $rows) {
            if (! array_key_exists($table, $restored)) {
                throw new RuntimeException("Table \"{$table}\" is missing from the restored database.");
            }

            if ($rows > 0 && $restored[$table] === 0) {
                throw new RuntimeException("Table \"{$table}\" had {$rows} rows when backed up but is empty after restore.");
            }
        }
    }

    private function verifyRepositories(string $diagramsDir): void
    {
        foreach (glob($diagramsDir.'/*/.git', GLOB_ONLYDIR) ?: [] as $gitDir) {
            $slug = basename(dirname($gitDir));

            try {
                ProcessRunner::run(['git', '--git-dir', $gitDir, 'fsck', '--no-progress']);
            } catch (ProcessFailedException $e) {
                throw new RuntimeException("git fsck failed for Diagram \"{$slug}\": ".trim($e->getProcess()->getErrorOutput()), previous: $e);
            }
        }
    }
}
