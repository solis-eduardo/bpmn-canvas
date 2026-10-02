<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * What an Administrator asked the User list to narrow down to (issue #151).
 * Filters combine with AND: `name` is a case-insensitive substring match on
 * the User's name or email; `loginMethod` is one recorded login method and
 * `admin` is whether the User is an Administrator (null when not filtered).
 */
final readonly class UserListFilters
{
    public function __construct(
        public string $name = '',
        public ?string $loginMethod = null,
        public ?bool $admin = null,
    ) {}

    /**
     * Reads the list's query string: "name", "login_method" and "admin"
     * ("1" or "0"). An unknown "login_method" or "admin" value is ignored.
     */
    public static function fromRequest(Request $request): self
    {
        $loginMethod = $request->query('login_method');
        $admin = $request->query('admin');

        return new self(
            name: trim((string) $request->query('name', '')),
            loginMethod: in_array($loginMethod, [User::LOGIN_METHOD_LOCAL, User::LOGIN_METHOD_KEYCLOAK], true) ? $loginMethod : null,
            admin: in_array($admin, ['0', '1'], true) ? $admin === '1' : null,
        );
    }

    public function isActive(): bool
    {
        return $this->name !== '' || $this->loginMethod !== null || $this->admin !== null;
    }
}
