<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\Concerns\SharedVersionRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * VersionRepository adapter that keeps the history in the `diagram_versions`
 * table, one row per Version holding its full content (ADR-0018). It spawns no
 * subprocess, so it works where there is no git binary (the browser demo on
 * php-wasm, with VERSION_STORE=database).
 *
 * The working copy stays on the diagrams disk, as with the git adapter: the
 * table only records what each Version contained.
 */
class DatabaseVersionRepository implements VersionRepository
{
    use SharedVersionRepository;

    private const TABLE = 'diagram_versions';

    /**
     * Only full identifiers are accepted here, as ADR-0018 records: the table
     * is keyed by the complete id, with no abbreviation to resolve.
     */
    protected function identifierPattern(): string
    {
        return '/^[0-9a-f]{40}$/i';
    }

    public function list(Diagram $diagram): array
    {
        $rows = $this->query($diagram)
            ->orderByDesc('id')
            ->get(['version_id', 'saved_at', 'author_name']);

        return $this->describeHistory($diagram, $rows->map(fn (object $row): array => [
            $row->version_id,
            Carbon::parse($row->saved_at),
            $row->author_name,
        ])->all());
    }

    public function exists(Diagram $diagram, string $version): bool
    {
        if (! $this->isValidIdentifier($version)) {
            return false;
        }

        return $this->query($diagram)->where('version_id', $version)->exists();
    }

    public function get(Diagram $diagram, string $version): string
    {
        $this->validateIdentifier($version);

        $content = $this->query($diagram)->where('version_id', $version)->value('content');

        if ($content === null) {
            throw new RuntimeException('Version not found.');
        }

        return $content;
    }

    protected function openHistory(Diagram $diagram): bool
    {
        return $this->query($diagram)->exists();
    }

    /**
     * Appends a Version holding the content currently on disk, unless it
     * repeats the last one (and $allowEmpty is false). The id is random rather
     * than derived from the content, so restoring an old content still yields a
     * new Version.
     */
    protected function recordVersion(Diagram $diagram, User $author, string $message, bool $allowEmpty): ?string
    {
        $content = Storage::disk('diagrams')->get($diagram->disk_path);

        $latest = $this->query($diagram)->orderByDesc('id')->first(['content']);
        $hasChanges = $latest === null || $latest->content !== $content;

        if (! $hasChanges && ! $allowEmpty) {
            return null;
        }

        $versionId = bin2hex(random_bytes(20));

        DB::table(self::TABLE)->insert([
            'diagram_id' => $diagram->id,
            'version_id' => $versionId,
            'content' => $content,
            'author_name' => $author->name,
            'message' => $message,
            'saved_at' => Carbon::now(),
        ]);

        return $versionId;
    }

    private function query(Diagram $diagram): Builder
    {
        return DB::table(self::TABLE)->where('diagram_id', $diagram->id);
    }
}
