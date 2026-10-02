<?php

namespace App\Repositories;

use Illuminate\Http\Request;

/**
 * Column and direction the Diagram list is ordered by (issue #39). Anything
 * the query string names outside the whitelist falls back to the defaults.
 */
final readonly class DiagramListSort
{
    /**
     * Sortable columns, each with its default direction when the query
     * string names none.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'title' => 'asc',
        'updated_at' => 'desc',
    ];

    public function __construct(
        public string $column = 'title',
        public string $direction = 'asc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $requested = (string) $request->query('sort', 'title');
        $column = array_key_exists($requested, self::COLUMNS) ? $requested : 'title';
        $direction = $request->query('direction');

        return new self($column, in_array($direction, ['asc', 'desc'], true) ? $direction : self::COLUMNS[$column]);
    }
}
