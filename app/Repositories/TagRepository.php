<?php

namespace App\Repositories;

use App\Models\Tag;
use Illuminate\Database\QueryException;

class TagRepository
{
    /**
     * Finds the Tag by name, creating it on first use (CONTEXT.md, "Tag").
     * `name` is unique, so a concurrent first use racing this method's own
     * select-then-insert can hit the DB constraint instead of the select —
     * that case still resolves to the row the other request just created,
     * rather than surfacing as an unhandled failure.
     */
    public function findOrCreateByName(string $name): Tag
    {
        try {
            return Tag::firstOrCreate(['name' => $name]);
        } catch (QueryException $exception) {
            return Tag::where('name', $name)->firstOr(fn () => throw $exception);
        }
    }
}
