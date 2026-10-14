<?php

namespace App\Support;

/**
 * The views shared by the server and standalone builds (ADR-0019) render the
 * same markup in both. The standalone page is rendered once per language at
 * build time (`php artisan standalone:render`), with this flag on, so links
 * point at its hash routes instead of server URLs.
 */
final class Ui
{
    private static bool $standalone = false;

    /** Where the standalone page's own routes live, by server route name. */
    private const STANDALONE_ROUTES = [
        'home' => '#/home',
        'diagrams.index' => '#/',
        'diagrams.create' => '#/diagrams/new',
        'diagrams.show' => '#/diagram/{slug}',
        'diagrams.edit' => '#/diagram/{slug}/edit',
        'workspaces.index' => '#/workspaces',
        'admin.users.index' => '#/users',
        'account.edit' => '#/account',
    ];

    public static function standalone(): bool
    {
        return self::$standalone;
    }

    public static function renderingStandalone(bool $on = true): void
    {
        self::$standalone = $on;
    }

    /**
     * A link target: the server route, or its hash route in the standalone
     * page. The Diagram parameter is the Slug, or a token the page fills in.
     */
    public static function route(string $name, string $slug = ''): string
    {
        if (! self::$standalone) {
            return $slug === '' ? route($name) : route($name, $slug);
        }

        return str_replace('{slug}', $slug, self::STANDALONE_ROUTES[$name]);
    }
}
