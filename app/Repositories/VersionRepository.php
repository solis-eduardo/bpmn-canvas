<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\DiagramVersionName;
use App\Models\User;
use App\Support\BpmnSearchableText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $versionId = $this->commit($diagram, $author, $message ?? self::MESSAGE_DEFAULT, $directory, $file, $allowEmpty);

        // After the Version is safely committed, not before: reindexing
        // ahead of a commit that then throws would leave searchable_text
        // describing content that was never actually saved as a Version.
        $this->reindexSearchableText($diagram, $content);

        return $versionId;
    }

    /**
     * Keeps Diagram::searchable_text (issue #41) in sync with the content
     * currently on disk — covers every path through save(): explicit saves,
     * restores (which call save() with the restored content), and
     * initialize() (which re-commits whatever's already on disk for a
     * brand-new or forked Diagram). $content is reused when the caller
     * already has it (avoids re-reading the file this same call just
     * wrote); only re-read from disk when save() was called without new
     * content (nothing was just written).
     *
     * A plain query builder update — not $diagram->save() — so this
     * doesn't touch updated_at ("last modified" in the Diagram list is
     * about the Diagram's own content, not this derived index) or fire
     * model events for a column nothing else observes.
     */
    private function reindexSearchableText(Diagram $diagram, ?string $content): void
    {
        $content ??= Storage::disk('diagrams')->get($diagram->disk_path);

        DB::table('diagrams')->where('id', $diagram->id)->update([
            'searchable_text' => is_string($content) ? BpmnSearchableText::extract($content) : '',
        ]);
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
     * Every Version is numbered sequentially within the Diagram, 1 being the
     * oldest (chronological order) — this makes numbering retroactive by
     * construction: it's derived from position in the Git history, not
     * stored anywhere. `name` is the custom name if the Version was renamed
     * (see rename()), otherwise the default "Version N"; `renamed` tells
     * these two cases apart, e.g. to highlight renamed Versions in the
     * history.
     *
     * @return array<int, array{id: string, saved_at: Carbon, author: string, number: int, name: string, renamed: bool}>
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

        $lines = explode("\n", $output);
        $total = count($lines);

        $customNames = DiagramVersionName::where('diagram_id', $diagram->id)
            ->pluck('name', 'version_id');

        return array_map(function (string $line, int $index) use ($total, $customNames) {
            [$id, $date, $author] = explode("\t", $line);

            // $index 0 is the newest (git log's default order), so the
            // oldest (chronologically first, index $total - 1) is number 1.
            $number = $total - $index;
            $customName = $customNames->get($id);

            return [
                'id' => $id,
                'saved_at' => Carbon::parse($date),
                'author' => $author,
                'number' => $number,
                'name' => $customName ?? self::defaultName($number),
                'renamed' => $customName !== null,
            ];
        }, $lines, array_keys($lines));
    }

    /**
     * Gives a Version a custom name to mark a milestone. Names needn't be
     * unique (CONTEXT.md, "Version") — the number still identifies the
     * Version. No Git tags are used to store it (ADR-0004): a dedicated
     * table keeps the name reachable/queryable outside of `git`.
     */
    public function rename(Diagram $diagram, string $version, string $name): void
    {
        // Confirms the Version actually exists in this Diagram's history
        // before naming it — same check restore() gets for free via get(),
        // otherwise a bogus/nonexistent id would silently succeed and leave
        // an orphaned, unreachable row behind.
        $this->get($diagram, $version);

        DiagramVersionName::updateOrCreate(
            ['diagram_id' => $diagram->id, 'version_id' => $version],
            ['name' => $name],
        );
    }

    public static function defaultName(int $number): string
    {
        return "Version {$number}";
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
     * Content of the Diagram at several Versions, keyed by id. Each Version
     * is an independent `git show` subprocess, so they're started
     * concurrently and only then waited on — used by compareVersions() to
     * resolve both sides of a comparison in one request without paying for
     * two sequential subprocess round trips.
     *
     * @param  array<int, string>  $versionIds
     * @return array<string, string>
     */
    public function getMany(Diagram $diagram, array $versionIds): array
    {
        foreach ($versionIds as $version) {
            $this->validateIdentifier($version);
        }

        $directory = $this->directory($diagram);
        $file = $this->file($diagram);

        $processes = [];

        foreach ($versionIds as $version) {
            $process = $this->git(['git', 'show', $version.':'.$file], $directory);
            $process->start();
            $processes[$version] = $process;
        }

        $content = [];

        foreach ($processes as $version => $process) {
            $process->wait();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Version not found.', previous: new ProcessFailedException($process));
            }

            $content[$version] = $process->getOutput();
        }

        return $content;
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
