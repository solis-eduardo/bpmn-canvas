<?php

namespace App\Repositories;

use Illuminate\Http\Request;

/**
 * What a User asked the Workspace list to narrow down to (issue #127). All
 * filters are case-insensitive substring matches and combine with AND:
 * `name` on the Workspace name, `owner`/`member` on a User's name or email,
 * `diagram` on the title or Slug of a Diagram held by the Workspace.
 */
final readonly class WorkspaceListFilters
{
    public function __construct(
        public string $name = '',
        public string $owner = '',
        public string $member = '',
        public string $diagram = '',
    ) {}

    /**
     * Reads the list's query string: "name", "owner", "member" and "diagram".
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: trim((string) $request->query('name', '')),
            owner: trim((string) $request->query('owner', '')),
            member: trim((string) $request->query('member', '')),
            diagram: trim((string) $request->query('diagram', '')),
        );
    }

    public function isActive(): bool
    {
        return $this->name !== '' || $this->owner !== '' || $this->member !== '' || $this->diagram !== '';
    }
}
