<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\Concerns\SharedVersionRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * The default VersionRepository adapter: keeps a Diagram's Version history
 * using a dedicated Git repository per Diagram, in the same directory where
 * the `.bpmn` file lives (see ADR-0004). No Git terms leave this class.
 */
class GitVersionRepository implements VersionRepository
{
    use SharedVersionRepository;

    protected function openHistory(Diagram $diagram): bool
    {
        return $this->ensureGitInit($this->directory($diagram));
    }

    /**
     * Git commits the file as it is on disk, which is what the shared save()
     * has just written.
     */
    protected function recordVersion(Diagram $diagram, User $author, string $message, bool $allowEmpty): ?string
    {
        return $this->commit($diagram, $author, $message, $this->directory($diagram), $this->file($diagram), $allowEmpty);
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

        // git log's default order is already most recent first.
        $entries = array_map(function (string $line): array {
            [$id, $date, $author] = explode("\t", $line);

            return [$id, Carbon::parse($date), $author];
        }, explode("\n", $output));

        return $this->describeHistory($diagram, $entries);
    }

    public function resolve(Diagram $diagram, string $version): ?string
    {
        if (! $this->isValidIdentifier($version)) {
            return null;
        }

        $process = $this->git(['git', 'rev-parse', '--verify', '--quiet', $version.'^{commit}'], $this->directory($diagram));
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

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
