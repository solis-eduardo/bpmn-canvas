<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Keeps a Diagram's Version history using a dedicated Git repository per
 * Diagram, in the same directory where the `.bpmn` file lives (see
 * ADR-0004). No Git terms should leak out of this class: the interface only
 * speaks in terms of "Version" — initialize, save, list, get, restore.
 */
class VersionRepository
{
    private const MESSAGE_DEFAULT = 'Saved version';

    private const MESSAGE_INITIAL = 'Initial version';

    private const MESSAGE_RESTORE = 'Restored a previous version';

    /**
     * Ensures the Diagram has a Version repository, registering the content
     * currently on disk as the first Version if the repository doesn't
     * exist yet (created via the CRUD from ticket 02, or via any other path
     * that doesn't go through this service, such as a Fork).
     */
    public function initialize(Diagram $diagram, User $author): void
    {
        $this->save($diagram, $author);
    }

    /**
     * Writes $content to disk (when given) and records the result as a new
     * Version. Ensures the Version repository first, capturing the content
     * that was already on disk as the initial Version BEFORE overwriting it
     * — that's why the disk write lives here, and not in the caller, which
     * would have no way to preserve that order.
     *
     * If the content is identical to the last Version, no new Version is
     * created (avoids empty Versions on repeated saves) — unless
     * $allowEmpty is true (used by restore, which must always create a new
     * Version on top of the history).
     *
     * @return string|null Opaque identifier of the created Version, or null
     *                     if nothing changed and no Version was created.
     */
    public function save(Diagram $diagram, User $author, ?string $content = null, ?string $message = null, bool $allowEmpty = false): ?string
    {
        $directory = $this->directory($diagram);
        $file = $this->file($diagram);

        $alreadyExisted = $this->ensureGitInit($directory);

        if (! $alreadyExisted && Storage::disk('diagrams')->exists($diagram->disk_path)) {
            $this->commit($diagram, $author, self::MESSAGE_INITIAL, $directory, $file);
        }

        if ($content !== null) {
            Storage::disk('diagrams')->put($diagram->disk_path, $content);
        }

        return $this->commit($diagram, $author, $message ?? self::MESSAGE_DEFAULT, $directory, $file, $allowEmpty);
    }

    private function commit(Diagram $diagram, User $author, string $message, string $directory, string $file, bool $allowEmpty = false): ?string
    {
        $this->git(['git', 'add', '--', $file], $directory)->mustRun();

        $diff = $this->git(['git', 'diff', '--cached', '--quiet'], $directory);
        $diff->run();

        $hasChanges = match ($diff->getExitCode()) {
            0 => false,
            1 => true,
            default => throw new ProcessFailedException($diff),
        };

        if (! $hasChanges && ! $allowEmpty) {
            return null;
        }

        $args = [
            'git',
            '-c', 'user.name='.$author->name,
            '-c', 'user.email='.$author->email,
            'commit',
            '-m', $message,
        ];

        if (! $hasChanges) {
            $args[] = '--allow-empty';
        }

        // Uses the application's clock (respects Carbon::setTestNow in
        // tests) instead of the system clock where the `git` process runs.
        $now = Carbon::now()->toIso8601String();

        $this->git($args, $directory, [
            'GIT_AUTHOR_DATE' => $now,
            'GIT_COMMITTER_DATE' => $now,
        ])->mustRun();

        return $this->currentVersion($diagram);
    }

    /**
     * Version history, most recent first. Grouping by day is the
     * responsibility of the caller (controller/view) — each item here
     * already carries the full date for that.
     *
     * @return array<int, array{id: string, saved_at: Carbon, author: string}>
     */
    public function list(Diagram $diagram): array
    {
        $directory = $this->directory($diagram);

        if (! is_dir($directory.'/.git')) {
            return [];
        }

        $process = $this->git(['git', 'log', '--pretty=format:%H%x09%cI%x09%an'], $directory);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        return array_map(function (string $line) {
            [$id, $date, $author] = explode("\t", $line);

            return [
                'id' => $id,
                'saved_at' => Carbon::parse($date),
                'author' => $author,
            ];
        }, explode("\n", $output));
    }

    /**
     * Content of the Diagram as it was in a specific Version.
     */
    public function get(Diagram $diagram, string $version): string
    {
        $this->validateIdentifier($version);

        $process = $this->git(['git', 'show', $version.':'.$this->file($diagram)], $this->directory($diagram));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Version not found.', previous: new ProcessFailedException($process));
        }

        return $process->getOutput();
    }

    /**
     * Restores the content of an old Version: reads that content, writes it
     * as the file's current content, and creates a new Version on top of
     * the history (revert-style). Never deletes intermediate Versions.
     */
    public function restore(Diagram $diagram, string $version, User $author): void
    {
        $content = $this->get($diagram, $version);

        $this->save($diagram, $author, $content, self::MESSAGE_RESTORE, allowEmpty: true);
    }

    /**
     * @return bool true if the repository already existed (nothing to do);
     *              false if it was just initialized now.
     */
    private function ensureGitInit(string $directory): bool
    {
        if (is_dir($directory.'/.git')) {
            return true;
        }

        $this->git(['git', 'init', '--quiet'], $directory)->mustRun();

        return false;
    }

    private function currentVersion(Diagram $diagram): string
    {
        $process = $this->git(['git', 'rev-parse', 'HEAD'], $this->directory($diagram));
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * @param  array<string, string>  $env
     */
    private function git(array $args, string $cwd, array $env = []): Process
    {
        // Never trust implicit cwd discovery: if the directory doesn't
        // really exist at this instant, fail loudly instead of letting the
        // child process fall back to PHP's cwd (see directory()).
        if (! is_dir($cwd)) {
            throw new RuntimeException("Version repository directory does not exist: {$cwd}");
        }

        $process = new Process($args, $cwd, $env ?: null);
        $process->setTimeout(30);

        return $process;
    }

    private function validateIdentifier(string $version): void
    {
        if (! preg_match('/^[0-9a-f]{4,40}$/i', $version)) {
            throw new RuntimeException('Invalid version identifier.');
        }
    }

    private function directory(Diagram $diagram): string
    {
        $relative = dirname($diagram->disk_path);

        // Ensures the directory exists BEFORE any git command runs in it.
        // Without this, if the directory doesn't exist (e.g. malformed
        // disk_path, or the file hasn't been written yet), PHP's proc_open
        // silently falls back to the parent process's cwd — which, when
        // running via `php artisan test`/`artisan serve`, is the project
        // root, making `git commit` happen in the PROJECT's repository
        // instead of the Diagram's isolated repository (real incident: see
        // history).
        Storage::disk('diagrams')->makeDirectory($relative);

        $path = Storage::disk('diagrams')->path($relative);

        if (! is_dir($path)) {
            throw new RuntimeException("Could not prepare the Version directory: {$path}");
        }

        return $path;
    }

    private function file(Diagram $diagram): string
    {
        return basename($diagram->disk_path);
    }
}
