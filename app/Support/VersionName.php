<?php

namespace App\Support;

/**
 * The default name of a Version without a custom one: "Version N", in the
 * page's language (see the `messages.version_number` translation).
 */
final class VersionName
{
    public static function default(int $number): string
    {
        return __('messages.version_number', ['number' => $number]);
    }
}
