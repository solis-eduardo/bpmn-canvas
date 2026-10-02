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
    public function __construct(
        public string $search = '',
        public string $tag = '',
        public ?int $workspaceId = null,
        public bool $withoutWorkspace = false,
        public bool $owner = false,
        public bool $collaborator = false,
        public bool $locked = false,
        public bool $recent = false,
    ) {}

    /**
     * Reads the list's query string: "q", "tag", "workspace" ("none" or a
     * Workspace id) and the "owner"/"collaborator"/"locked"/"recent" flags.
     */
    public static function fromRequest(Request $request): self
    {
        $workspace = (string) $request->query('workspace', 'all');

        return new self(
            search: trim((string) $request->query('q', '')),
            tag: trim((string) $request->query('tag', '')),
            workspaceId: ctype_digit($workspace) && (int) $workspace > 0 ? (int) $workspace : null,
            withoutWorkspace: $workspace === 'none',
            owner: $request->boolean('owner'),
            collaborator: $request->boolean('collaborator'),
            locked: $request->boolean('locked'),
            recent: $request->boolean('recent'),
        );
    }
}
