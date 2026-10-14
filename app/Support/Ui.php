<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Lets the views shared by the server and standalone builds (ADR-0019) say
 * their text once. On the server the strings are translated while rendering;
 * in the standalone page, rendered once at build time, they carry a
 * `data-i18n` key that standalone/main.js fills in for the chosen language.
 */
final class Ui
{
    private static bool $standalone = false;

    public static function standalone(): bool
    {
        return self::$standalone;
    }

    public static function renderingStandalone(bool $on = true): void
    {
        self::$standalone = $on;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function text(string $key, array $replace = []): HtmlString
    {
        return new HtmlString(self::$standalone
            ? '<span data-i18n="'.e($key).'"></span>'
            : e(__($key, $replace)));
    }

    /**
     * One or more attributes (e.g. title, aria-label) taking the same string.
     */
    public static function attr(string $key, string ...$names): HtmlString
    {
        $names = $names ?: ['title', 'aria-label'];

        return new HtmlString(collect($names)->map(fn (string $name): string => self::$standalone
            ? ' data-i18n-'.$name.'="'.e($key).'"'
            : ' '.$name.'="'.e(__($key)).'"')->implode(''));
    }
}
