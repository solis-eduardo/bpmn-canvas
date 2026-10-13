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

    private const MESSAGE_DEFAULT = 'Saved version';

    private const MESSAGE_INITIAL = 'Initial version';

    private const MESSAGE_RESTORE = 'Restored a previous version';

    public function initialize(Diagram $diagram, User $author): void
    {
        $this->save($diagram, $author);
    }

    public function save(Diagram $diagram, User $author, ?string $content = null, ?string $message = null, bool $allowEmpty = false): ?string
    {
        $disk = Storage::disk('diagrams');

        // The content already on disk becomes the initial Version BEFORE it is
        // overwritten, exactly like the git adapter.
        if (! $this->hasHistory($diagram) && $disk->exists($diagram->disk_path)) {
            $this->record($diagram, $author, self::MESSAGE_INITIAL, $disk->get($diagram->disk_path), allowEmpty: false);
        }

        if ($content !== null) {
            $disk->put($diagram->disk_path, $content);
        }

        $versionId = $this->record($diagram, $author, $message ?? self::MESSAGE_DEFAULT, $disk->get($diagram->disk_path), $allowEmpty);

        $this->afterCommit($diagram, $content);

        return $versionId;
    }

    public function list(Diagram $diagram): array
    {
        $rows = DB::table(self::TABLE)
            ->where('diagram_id', $diagram->id)
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
        if (! preg_match('/^[0-9a-f]{4,40}$/i', $version)) {
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

    public function getMany(Diagram $diagram, array $versionIds): array
    {
        $content = [];

        foreach ($versionIds as $version) {
            $content[$version] = $this->get($diagram, $version);
        }

        return $content;
    }

    /**
     * Appends a Version holding $content, unless it repeats the last one (and
     * $allowEmpty is false). The id is random rather than derived from the
     * content, so restoring an old content still yields a new Version.
     */
    private function record(Diagram $diagram, User $author, string $message, string $content, bool $allowEmpty): ?string
    {
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

    private function hasHistory(Diagram $diagram): bool
    {
        return $this->query($diagram)->exists();
    }

    private function query(Diagram $diagram): Builder
    {
        return DB::table(self::TABLE)->where('diagram_id', $diagram->id);
    }
}
