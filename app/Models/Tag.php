<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Tag extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsToMany<Diagram, $this> */
    public function diagrams(): BelongsToMany
    {
        return $this->belongsToMany(Diagram::class);
    }
}
