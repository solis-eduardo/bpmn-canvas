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
     * Sortable columns (header clicks) with their default direction.
     *
     * @var array<string, string>
     */
    public const SORT_COLUMNS = ['title' => 'asc', 'updated_at' => 'desc'];

    public function __construct(
        public string $search = '',
        public DiagramListFacets $facets = new DiagramListFacets,
        public ?int $workspaceId = null,
        public bool $withoutWorkspace = false,
        public bool $owner = false,
        public bool $collaborator = false,
        public ListSort $sort = new ListSort(self::SORT_COLUMNS, 'title', 'asc'),
    ) {}

    /**
     * Reads the list's query string: "q", "tags[]" (a Diagram must carry every
     * Tag), "owner_id" and "author_id" (one User each), "workspace" ("none" or
     * a Workspace id) and the "owner"/"collaborator" flags, and
     * "sort"/"direction" (falling back to the title, ascending).
     */
    public static function fromRequest(Request $request): self
    {
        $workspace = (string) $request->query('workspace', 'all');

        return new self(
            search: trim((string) $request->query('q', '')),
            facets: new DiagramListFacets(
                tags: self::tagsFrom($request),
                ownerId: self::userIdFrom($request, 'owner_id'),
                authorId: self::userIdFrom($request, 'author_id'),
            ),
            workspaceId: ctype_digit($workspace) && (int) $workspace > 0 ? (int) $workspace : null,
            withoutWorkspace: $workspace === 'none',
            owner: $request->boolean('owner'),
            collaborator: $request->boolean('collaborator'),
            sort: ListSort::fromRequest($request, self::SORT_COLUMNS),
        );
    }

    /**
     * @return list<string>
     */
    private static function tagsFrom(Request $request): array
    {
        $tags = $request->query('tags', []);

        return collect(is_array($tags) ? $tags : [$tags])
            ->filter(fn ($tag) => is_string($tag))
            ->map(fn (string $tag) => trim($tag))
            ->filter(fn (string $tag) => $tag !== '')
            ->unique()
            ->values()
            ->all();
    }

    private static function userIdFrom(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * The request's query string with the legacy "discover" param folded into
     * "q" (discovery is part of the unified search) and paging dropped.
     *
     * @return array<string, mixed>
     */
    public static function unifiedSearchQuery(Request $request): array
    {
        $query = $request->query();
        $search = trim((string) ($query['q'] ?? $query['discover'] ?? ''));
        unset($query['q'], $query['discover'], $query['page'], $query['discovery_page']);

        return $search === '' ? $query : ['q' => $search] + $query;
    }
}
