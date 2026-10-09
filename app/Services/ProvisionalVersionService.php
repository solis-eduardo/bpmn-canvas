<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\DiagramVersionName;
use App\Repositories\VersionRepository;

/**
 * Keeps the Lock holder's unsaved Editor content as a provisional Version,
 * authored by the holder (ADR-0003). Shared by Lock expiry and manual
 * release, so both save it the same way.
 */
class ProvisionalVersionService
{
    public function __construct(private readonly VersionRepository $versions) {}

    /**
     * Saves $draft, or the draft the server holds when none is given, as a
     * provisional Version in the holder's name. Does nothing if there is no
     * holder or content, or if the holder can no longer edit the Diagram.
     */
    public function save(Diagram $diagram, ?string $draft = null): void
    {
        $holder = $diagram->lockedBy;
        $draft ??= $diagram->lock_draft;

        if ($holder === null || blank($draft)) {
            return;
        }

        // The draft was accepted while the holder could edit, but release
        // happens later and rights can lapse in between. Committing it would
        // write content as a User who may no longer edit this Diagram, so a
        // lapsed holder's draft is dropped instead.
        if (! $diagram->isEditableBy($holder)) {
            return;
        }

        $versionId = $this->versions->save($diagram, $holder, $draft, VersionRepository::MESSAGE_PROVISIONAL);

        if ($versionId !== null) {
            DiagramVersionName::updateOrCreate(
                ['diagram_id' => $diagram->id, 'version_id' => $versionId],
                ['name' => __('messages.provisional_version')],
            );
        }
    }
}
