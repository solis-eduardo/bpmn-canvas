<?php

namespace App\Repositories;

use Illuminate\Http\Request;

/**
 * Column and direction a listing is ordered by, driven by its clickable
 * table headers through the "sort" and "direction" query parameters. Each
 * listing declares its own allowlist of sortable columns; anything the query
 * string names outside it falls back to the first column and its default
 * direction, so a column name never reaches SQL unchecked.
 */
final readonly class ListSort
{
    /**
     * @param  array<string, string>  $columns  Sortable columns, each with its default direction; the first is the default sort.
     */
    public function __construct(
        public array $columns,
        public string $column,
        public string $direction,
    ) {}

    /**
     * @param  array<string, string>  $columns
     */
    public static function fromRequest(Request $request, array $columns): self
    {
        $requested = $request->query('sort');
        $column = is_string($requested) && array_key_exists($requested, $columns) ? $requested : (string) array_key_first($columns);
        $direction = $request->query('direction');

        return new self($columns, $column, in_array($direction, ['asc', 'desc'], true) ? $direction : $columns[$column]);
    }

    public function isActive(string $column): bool
    {
        return $this->column === $column;
    }

    /**
     * The direction a click on $column's header asks for: the opposite of the
     * current one when it is already the sorted column, else its default.
     */
    public function nextDirection(string $column): string
    {
        if (! $this->isActive($column)) {
            return $this->columns[$column];
        }

        return $this->direction === 'asc' ? 'desc' : 'asc';
    }
}
