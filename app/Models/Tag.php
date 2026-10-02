<?php

namespace App\Models;

use Spatie\Tags\Tag as SpatieTag;

/**
 * A global, free-text Tag (CONTEXT.md, "Tag"), backed by spatie/laravel-tags
 * (ADR-0014). Tags are user-typed labels, not translated content, so every
 * lookup and creation is pinned to a single locale instead of following the
 * request's — otherwise the same label typed under two UI languages would
 * become two Tags.
 */
class Tag extends SpatieTag
{
    public const LOCALE = 'en';

    public static function getLocale(): string
    {
        return self::LOCALE;
    }
}
