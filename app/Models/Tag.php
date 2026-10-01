<?php

namespace App\Models;

use App\Repositories\TagRepository;
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

    /**
     * Kept for existing callers; the logic lives in TagRepository.
     */
    public static function findOrCreateByName(string $name): self
    {
        return app(TagRepository::class)->findOrCreateByName($name);
    }
}
