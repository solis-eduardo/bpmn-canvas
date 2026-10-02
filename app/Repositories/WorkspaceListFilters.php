<?php

namespace App\Repositories;

use Illuminate\Http\Request;

/**
 * What a User asked the Workspace list to narrow down to (issues #127, #148).
 * Filters combine with AND: `name` is a case-insensitive substring match on
 * the Workspace name; `owner` and `member` are the ID of an existing User
 * picked from a list (null when no User is selected).
 */
final readonly class WorkspaceListFilters
{
    public function __construct(
        public string $name = '',
        public ?int $owner = null,
        public ?int $member = null,
    ) {}

    /**
     * Reads the list's query string: "name", "owner" and "member". A
     * non-numeric "owner"/"member" is ignored.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: trim((string) $request->query('name', '')),
            owner: self::userId($request->query('owner')),
            member: self::userId($request->query('member')),
        );
    }

    public function isActive(): bool
    {
        return $this->name !== '' || $this->owner !== null || $this->member !== null;
    }

    private static function userId(mixed $value): ?int
    {
        $value = is_string($value) ? trim($value) : '';

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
