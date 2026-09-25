<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom name given to a Version, keyed by the Diagram and the Version's
 * opaque identifier (a Git commit hash — see VersionRepository). A Version
 * without a row here has no custom name and falls back to the default
 * "Version N".
 */
#[Fillable(['diagram_id', 'version_id', 'name'])]
class DiagramVersionName extends Model
{
    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }
}
