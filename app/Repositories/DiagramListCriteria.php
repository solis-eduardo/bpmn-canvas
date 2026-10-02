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
     * @param  list<string>  $tags  Tag names a Diagram must all carry
     * @param  ?int  $ownerId  the Diagram Owner to narrow to, if any
     * @param  ?int  $authorId  the Author to narrow to, if any
     */
    public function __construct(
        public string $search = '',
        public array $tags = [],
        public ?int $ownerId = null,
        public ?int $authorId = null,
        public ?int $workspaceId = null,
        public bool $withoutWorkspace = false,
        public bool $owner = false,
        public bool $collaborator = false,
        public bool $locked = false,
        public bool $recent = false,
        public DiagramListSort $sort = new DiagramListSort,
    ) {}

    /**
     * Reads the list's query string: "q", "tags[]" (a Diagram must carry every
     * Tag), "owner_id" and "author_id" (one User each), "workspace" ("none" or
     * a Workspace id) and the "owner"/"collaborator"/"locked"/"recent" flags, and
     * "sort"/"direction" (falling back to the title, ascending).
     */
    public static function fromRequest(Request $request): self
    {
        $workspace = (string) $request->query('workspace', 'all');

        return new self(
            search: trim((string) $request->query('q', '')),
            tags: self::tagsFrom($request),
            ownerId: self::userIdFrom($request, 'owner_id'),
            authorId: self::userIdFrom($request, 'author_id'),
            workspaceId: ctype_digit($workspace) && (int) $workspace > 0 ? (int) $workspace : null,
            withoutWorkspace: $workspace === 'none',
            owner: $request->boolean('owner'),
            collaborator: $request->boolean('collaborator'),
            locked: $request->boolean('locked'),
            recent: $request->boolean('recent'),
            sort: DiagramListSort::fromRequest($request),
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
