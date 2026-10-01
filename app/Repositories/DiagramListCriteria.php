<?php

namespace App\Repositories;

use Illuminate\Http\Request;

/**
 * What a User asked the Diagram list to narrow down to (issues #38-#41).
 * `workspaceId` and `withoutWorkspace` are mutually exclusive; both unset
 * means "every Workspace".
 */
final readonly class DiagramListCriteria
{
    /**
     * Sortable columns, each with its default direction when the query
     * string names none.
     *
     * @var array<string, string>
     */
    public const SORT_COLUMNS = [
        'title' => 'asc',
        'updated_at' => 'desc',
    ];

    public function __construct(
        public string $search = '',
        public string $tag = '',
        public ?int $workspaceId = null,
        public bool $withoutWorkspace = false,
        public bool $owner = false,
        public bool $collaborator = false,
        public bool $locked = false,
        public bool $recent = false,
        public string $sort = 'title',
        public string $direction = 'asc',
    ) {}

    /**
     * Reads the list's query string: "q", "tag", "workspace" ("none" or a
     * Workspace id) and the "owner"/"collaborator"/"locked"/"recent" flags, and
     * "sort"/"direction" (falling back to the title, ascending).
     */
    public static function fromRequest(Request $request): self
    {
        $workspace = (string) $request->query('workspace', 'all');
        $requestedSort = (string) $request->query('sort', 'title');
        $sort = array_key_exists($requestedSort, self::SORT_COLUMNS) ? $requestedSort : 'title';
        $requestedDirection = $request->query('direction');

        return new self(
            search: trim((string) $request->query('q', '')),
            tag: trim((string) $request->query('tag', '')),
            workspaceId: ctype_digit($workspace) && (int) $workspace > 0 ? (int) $workspace : null,
            withoutWorkspace: $workspace === 'none',
            owner: $request->boolean('owner'),
            collaborator: $request->boolean('collaborator'),
            locked: $request->boolean('locked'),
            recent: $request->boolean('recent'),
            sort: $sort,
            direction: in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : self::SORT_COLUMNS[$sort],
        );
    }

    /**
     * The "discover" query param: the term searched among Diagrams outside
     * the User's reach, kept across the list and the access-request redirect.
     */
    public static function discoverSearch(Request $request): string
    {
        return trim((string) $request->query('discover', ''));
    }
}
