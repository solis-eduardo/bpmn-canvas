<?php

namespace App\Repositories\Concerns;

use App\Models\Diagram;
use App\Models\DiagramVersionName;
use App\Models\User;
use App\Support\BpmnSearchableText;
use App\Support\VersionName;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Behaviour every VersionRepository adapter shares: the Version state kept in
 * `diagram_version_names` (names, provisional and hidden flags), the numbering
 * and naming of the history, restore, and the bookkeeping that follows a
 * committed save. An adapter only has to store and read Versions itself.
 *
 * Expects the using class to provide get() and save().
 */
trait SharedVersionRepository
{
    /**
     * Gives a Version a custom name to mark a milestone (see the interface).
     * No storage tag is used for it (ADR-0004): a dedicated table keeps the
     * name reachable/queryable outside of the storage engine.
     */
    public function rename(Diagram $diagram, string $version, string $name): void
    {
        // Confirms the Version actually exists in this Diagram's history
        // before naming it, otherwise a bogus id would leave an orphaned row.
        $this->get($diagram, $version);

        $this->setState($diagram, $version, ['name' => $name]);
    }

    public function markProvisional(Diagram $diagram, string $version): void
    {
        $this->setState($diagram, $version, ['is_provisional' => true]);
    }

    public function makePermanent(Diagram $diagram, string $version): bool
    {
        return $this->clearProvisional($diagram, $version, ['is_provisional' => false]);
    }

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
     * @param  iterable<int, array{0: string, 1: CarbonInterface, 2: string}>  $newestFirst
     * @return array<int, array{id: string, saved_at: CarbonInterface, author: string, number: int, name: string, renamed: bool, provisional: bool, hidden: bool}>
     */
    protected function describeHistory(Diagram $diagram, iterable $newestFirst): array
    {
        $entries = [];
        foreach ($newestFirst as $entry) {
            $entries[] = $entry;
        }

        if ($entries === []) {
            return [];
        }

        $total = count($entries);
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
        }, $entries, array_keys($entries));
    }

    /**
     * Keeps Diagram::searchable_text (issue #41) in sync with the content on
     * disk, and settles the Lock draft. Called by an adapter only once the
     * Version is safely recorded: reindexing ahead of a commit that then throws
     * would leave searchable_text describing content that was never saved.
     */
    protected function afterCommit(Diagram $diagram, ?string $content): void
    {
        // $content is reused when the caller has it; otherwise the content on
        // disk is indexed. Only an explicit save settles the holder's draft.
        $indexed = $content ?? Storage::disk('diagrams')->get($diagram->disk_path);

        // A plain query builder update, so "last modified" (updated_at) keeps
        // meaning the Diagram's own content, not this derived index.
        DB::table('diagrams')->where('id', $diagram->id)->update([
            'searchable_text' => is_string($indexed) ? BpmnSearchableText::extract($indexed) : '',
        ]);

        // Settling the content settles the holder's draft with it (ADR-0003).
        if ($content !== null) {
            $diagram->clearLockDraft();
        }
    }

    protected function validateIdentifier(string $version): void
    {
        if (! preg_match('/^[0-9a-f]{4,40}$/i', $version)) {
            throw new RuntimeException('Invalid version identifier.');
        }
    }

    /**
     * @param  array<string, bool|string>  $state
     */
    private function clearProvisional(Diagram $diagram, string $version, array $state): bool
    {
        $this->get($diagram, $version);

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
