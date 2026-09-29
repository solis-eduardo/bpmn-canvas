<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;

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
     * Finds the Tag by name, creating it on first use (CONTEXT.md, "Tag").
     * `name` is unique, so a concurrent first use racing this method's own
     * select-then-insert can hit the DB constraint instead of the select —
     * that case still resolves to the row the other request just created,
     * rather than surfacing as an unhandled failure.
     */
    public static function findOrCreateByName(string $name): self
    {
        try {
            return static::firstOrCreate(['name' => $name]);
        } catch (QueryException $exception) {
            return static::where('name', $name)->firstOr(fn () => throw $exception);
        }
    }
}
