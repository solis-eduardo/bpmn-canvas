<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The port through which the application keeps a Diagram's Version history
 * (ADR-0004, ADR-0018). Callers only speak in terms of "Version": no storage
 * vocabulary leaks out of an adapter. Two adapters implement it:
 *
 * - GitVersionRepository (default): one Git repository per Diagram.
 * - DatabaseVersionRepository (VERSION_STORE=database): rows in the database,
 *   with no subprocesses, for the browser demo.
 *
 * Both must pass the shared contract suite (VersionRepositoryContract).
 */
interface VersionRepository
{
    /**
     * Commit message for the Version an expiring Lock leaves behind
     * (ADR-0003). Public because LockExpiryService is the one caller that
     * passes its own message; the User-facing name of that Version is the
     * localized `messages.provisional_version`, not this.
     */
    public const MESSAGE_PROVISIONAL = 'Provisional version (Lock expired)';

    /**
     * Ensures the Diagram has a Version history, registering the content
     * currently on disk as the first Version if there is none yet (created via
     * the CRUD, or via any other path that doesn't go through this service,
     * such as a Fork).
     */
    public function initialize(Diagram $diagram, User $author): void;

    /**
     * Writes $content to disk (when given) and records the result as a new
     * Version, capturing the content that was already on disk as the initial
     * Version BEFORE overwriting it.
     *
     * If the content is identical to the last Version, no new Version is
     * created — unless $allowEmpty is true (used by restore).
     *
     * @return string|null Opaque identifier of the created Version, or null
     *                     if nothing changed and no Version was created.
     */
    public function save(Diagram $diagram, User $author, ?string $content = null, ?string $message = null, bool $allowEmpty = false): ?string;

    /**
     * Version history, most recent first. Every Version is numbered
     * sequentially within the Diagram, 1 being the oldest. `name` is the custom
     * name if the Version was renamed, otherwise the default "Version N" (or
     * the localized provisional label for a provisional Version). Hidden
     * Versions are returned flagged `hidden`; the history shown to the User
     * filters them out.
     *
     * @return array<int, array{id: string, saved_at: CarbonInterface, author: string, number: int, name: string, renamed: bool, provisional: bool, hidden: bool}>
     */
    public function list(Diagram $diagram): array;

    /**
     * Gives a Version a custom name to mark a milestone. Names needn't be
     * unique: the number still identifies the Version.
     */
    public function rename(Diagram $diagram, string $version, string $name): void;

    /**
     * Flags a Version as provisional: content an expiring Lock kept for its
     * holder (ADR-0003).
     */
    public function markProvisional(Diagram $diagram, string $version): void;

    /**
     * Keeps a provisional Version: it becomes an ordinary Version.
     *
     * @return bool false if the Version isn't provisional (nothing changed).
     */
    public function makePermanent(Diagram $diagram, string $version): bool;

    /**
     * Soft-deletes a provisional Version: it is hidden from the history but
     * stays reachable, so numbering and Shares pinned to it keep working.
     *
     * @return bool false if the Version isn't provisional (nothing changed).
     */
    public function hide(Diagram $diagram, string $version): bool;

    /**
     * The full identifier of the Version $version designates (it may be an
     * abbreviation the store accepts), or null when it isn't a Version of this
     * Diagram. Callers that persist or compare an id use this form.
     */
    public function resolve(Diagram $diagram, string $version): ?string;

    /**
     * True if $version is a Version of this Diagram.
     */
    public function exists(Diagram $diagram, string $version): bool;

    /**
     * Content of the Diagram as it was in a specific Version.
     *
     * @throws \RuntimeException if the Version doesn't exist in this Diagram.
     */
    public function get(Diagram $diagram, string $version): string;

    /**
     * Restores the content of an old Version and creates a new Version on top
     * of the history (revert-style). Never deletes intermediate Versions.
     */
    public function restore(Diagram $diagram, string $version, User $author): void;
}
