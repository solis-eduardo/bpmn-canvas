<?php

namespace App\Repositories\Concerns;

use App\Exceptions\VersionHistoryBusyException;
use App\Models\Diagram;
use App\Models\DiagramVersionName;
use App\Models\User;
use App\Support\BpmnSearchableText;
use App\Support\VersionName;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Behaviour every VersionRepository adapter shares: the save orchestration
 * (initial Version, write, record, bookkeeping), the Version state kept in
 * `diagram_version_names` (names, provisional and hidden flags), the numbering
 * and naming of the history, and restore. An adapter only supplies the storage
 * steps: openHistory(), recordVersion(), findVersion(), get() and list().
 */
trait SharedVersionRepository
{
    private const MESSAGE_DEFAULT = 'Saved version';

    private const MESSAGE_INITIAL = 'Initial version';

    private const MESSAGE_RESTORE = 'Restored a previous version';

    /**
     * Seconds after which the history lock lapses on its own, so a writer that
     * dies mid-save cannot block the Diagram forever.
     */
    private const LOCK_TTL_SECONDS = 120;

    /**
     * Makes the Diagram's history ready to be written. Returns true when the
     * history already existed before this call.
     */
    abstract protected function openHistory(Diagram $diagram): bool;

    /**
     * The full identifier of a well-formed $version in this Diagram's history
     * (resolving an abbreviation where the store accepts one), or null.
     */
    abstract protected function findVersion(Diagram $diagram, string $version): ?string;

    /**
     * Records the Diagram's content currently on disk as a new Version, unless
     * it repeats the last one and $allowEmpty is false. Returns the new
     * Version's identifier, or null when nothing was recorded.
     */
    abstract protected function recordVersion(Diagram $diagram, User $author, string $message, bool $allowEmpty): ?string;

    public function initialize(Diagram $diagram, User $author): void
    {
        $this->save($diagram, $author);
    }

    /**
     * The content already on disk becomes the initial Version BEFORE it is
     * overwritten, so the first save never loses what was there.
     *
     * Recording a Version is serialized per Diagram (issue #242): the request
     * and the scheduler (locks:expire) share the cache store, so the lock holds
     * across processes. The file write and the commit form one critical section.
     * Version state changes (names, provisional and hidden flags) are not locked.
     *
     * @throws VersionHistoryBusyException if the lock stayed taken for the whole
     *                                     wait (config `bpmn.version_lock_wait_seconds`).
     *                                     Nothing was written; each caller decides
     *                                     how to retry (ADR-0018).
     */
    public function save(Diagram $diagram, User $author, ?string $content = null, ?string $message = null, bool $allowEmpty = false): ?string
    {
        try {
            return Cache::lock('diagram-versions:'.$diagram->getKey(), self::LOCK_TTL_SECONDS)
                ->block(config('bpmn.version_lock_wait_seconds'), fn (): ?string => $this->writeVersion($diagram, $author, $content, $message, $allowEmpty));
        } catch (LockTimeoutException $timeout) {
            throw VersionHistoryBusyException::fromTimeout($timeout);
        }
    }

    private function writeVersion(Diagram $diagram, User $author, ?string $content, ?string $message, bool $allowEmpty): ?string
    {
        $disk = Storage::disk('diagrams');

        $existed = $this->openHistory($diagram);

        if (! $existed && $disk->exists($diagram->disk_path)) {
            $this->recordVersion($diagram, $author, self::MESSAGE_INITIAL, allowEmpty: false);
        }

        if ($content !== null) {
            $disk->put($diagram->disk_path, $content);
        }

        $versionId = $this->recordVersion($diagram, $author, $message ?? self::MESSAGE_DEFAULT, $allowEmpty);

        $this->afterCommit($diagram, $content, recorded: $versionId !== null);

        return $versionId;
    }

    /**
     * Gives a Version a custom name to mark a milestone (see the interface).
     * No storage tag is used for it (ADR-0004): a dedicated table keeps the
     * name reachable/queryable outside of the storage engine.
     */
    public function rename(Diagram $diagram, string $version, string $name): void
    {
        // requireId() confirms the Version exists and yields its full id, so
        // neither a bogus id nor an abbreviation leaves an orphaned row.
        $this->setState($diagram, $this->requireId($diagram, $version), ['name' => $name]);
    }

    public function markProvisional(Diagram $diagram, string $version): void
    {
        $this->setState($diagram, $this->requireId($diagram, $version), ['is_provisional' => true]);
    }

    public function makePermanent(Diagram $diagram, string $version): bool
    {
        return $this->clearProvisional($diagram, $version, ['is_provisional' => false]);
    }

    /**
     * Flags a provisional Version as hidden. It also stops being provisional,
     * so the history keeps it as a regular, hidden Version.
     */
    public function hide(Diagram $diagram, string $version): bool
    {
        return $this->clearProvisional($diagram, $version, ['is_provisional' => false, 'is_hidden' => true]);
    }

    public function restore(Diagram $diagram, string $version, User $author): void
    {
        $content = $this->get($diagram, $version);

        $this->save($diagram, $author, $content, self::MESSAGE_RESTORE, allowEmpty: true);
    }

    /**
     * Turns the adapter's Versions (most recent first, as [id, saved_at,
     * author] rows) into the history shape: numbering derived from position,
     * and names/flags from the state table.
     *
     * @param  array<int, array{0: string, 1: CarbonInterface, 2: string}>  $newestFirst
     * @return array<int, array{id: string, saved_at: CarbonInterface, author: string, number: int, name: string, renamed: bool, provisional: bool, hidden: bool}>
     */
    protected function describeHistory(Diagram $diagram, array $newestFirst): array
    {
        $total = count($newestFirst);

        if ($total === 0) {
            return [];
        }

        $states = DiagramVersionName::where('diagram_id', $diagram->id)->get()->keyBy('version_id');

        return array_map(function (array $entry, int $index) use ($total, $states): array {
            [$id, $savedAt, $author] = $entry;

            // $index 0 is the newest, so the oldest (index $total - 1) is number 1.
            $number = $total - $index;
            $state = $states->get($id);
            $customName = $state?->name;
            $provisional = (bool) $state?->is_provisional;

            return [
                'id' => $id,
                'saved_at' => $savedAt,
                'author' => $author,
                'number' => $number,
                'name' => $customName ?? ($provisional ? __('messages.provisional_version') : VersionName::default($number)),
                'renamed' => $customName !== null,
                'provisional' => $provisional,
                'hidden' => (bool) $state?->is_hidden,
            ];
        }, $newestFirst, array_keys($newestFirst));
    }

    /**
     * Keeps Diagram::searchable_text (issue #41) in sync with the content on
     * disk, bumps updated_at when a Version was recorded (issue #220), and
     * settles the Lock draft. Called by an adapter only once the Version is
     * safely recorded: reindexing ahead of a commit that then throws would
     * leave searchable_text describing content that was never saved.
     */
    protected function afterCommit(Diagram $diagram, ?string $content, bool $recorded): void
    {
        // $content is reused when the caller has it; otherwise the content on
        // disk is indexed. Only an explicit save settles the holder's draft.
        $indexed = $content ?? Storage::disk('diagrams')->get($diagram->disk_path);

        // A plain query builder update, so "last modified" (updated_at) keeps
        // meaning the Diagram's own content, not this derived index. A new
        // Version is the one thing that does count as a change to the Diagram
        // (issue #220); Lock writes never do.
        $changes = [
            'searchable_text' => is_string($indexed) ? BpmnSearchableText::extract($indexed) : '',
        ];

        if ($recorded) {
            $changes['updated_at'] = now();
        }

        DB::table('diagrams')->where('id', $diagram->id)->update($changes);

        // Settling the content settles the holder's draft with it (ADR-0003).
        if ($content !== null) {
            $diagram->clearLockDraft();
        }
    }

    /**
     * The shape of a valid Version identifier. Adapters narrow it when they
     * accept fewer forms (the database store only takes full 40-character ids).
     */
    protected function identifierPattern(): string
    {
        return '/^[0-9a-f]{4,40}$/i';
    }

    protected function isValidIdentifier(string $version): bool
    {
        return (bool) preg_match($this->identifierPattern(), $version);
    }

    protected function validateIdentifier(string $version): void
    {
        if (! $this->isValidIdentifier($version)) {
            throw new RuntimeException('Invalid version identifier.');
        }
    }

    public function resolve(Diagram $diagram, string $version): ?string
    {
        return $this->isValidIdentifier($version) ? $this->findVersion($diagram, $version) : null;
    }

    public function exists(Diagram $diagram, string $version): bool
    {
        return $this->resolve($diagram, $version) !== null;
    }

    /**
     * The single entry point for an incoming Version id: validates it and
     * returns the full id, which is what the state table must be keyed by (an
     * abbreviation would leave an orphan row).
     *
     * @throws RuntimeException if the id is malformed or not a Version of the Diagram.
     */
    protected function requireId(Diagram $diagram, string $version): string
    {
        $this->validateIdentifier($version);

        return $this->findVersion($diagram, $version) ?? throw new RuntimeException('Version not found.');
    }

    /**
     * @param  array<string, bool|string>  $state
     */
    private function clearProvisional(Diagram $diagram, string $version, array $state): bool
    {
        $version = $this->requireId($diagram, $version);

        return DiagramVersionName::where('diagram_id', $diagram->id)
            ->where('version_id', $version)
            ->where('is_provisional', true)
            ->update($state) > 0;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function setState(Diagram $diagram, string $version, array $state): void
    {
        DiagramVersionName::updateOrCreate(
            ['diagram_id' => $diagram->id, 'version_id' => $version],
            $state,
        );
    }
}
