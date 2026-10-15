<?php

namespace App\Models;

use App\Enums\ProvisionalReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom name given to a Version, keyed by the Diagram and the Version's
 * opaque identifier (a Git commit hash — see VersionRepository). A Version
 * without a row here has no custom name and falls back to the default
 * "Version N". The row also carries the Version's state: provisional (kept
 * by an expiring Lock, not yet made permanent) and hidden (deleted from the
 * history without discarding it, see ADR-0004).
 */
#[Fillable(['diagram_id', 'version_id', 'name', 'is_provisional', 'is_hidden', 'kept_for_id', 'provisional_reason'])]
class DiagramVersionName extends Model
{
    protected function casts(): array
    {
        return ['is_provisional' => 'boolean', 'is_hidden' => 'boolean', 'kept_for_id' => 'integer', 'provisional_reason' => ProvisionalReason::class];
    }

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }
}
