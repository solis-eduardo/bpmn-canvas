<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slugger
{
    /**
     * Normalizes a free-form name into a [a-z0-9-] slug: lowercase, no
     * accents, spaces/symbols become "-", no "-" at the ends.
     */
    public static function slugify(string $text): string
    {
        $ascii = Str::ascii($text);
        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug === '' ? 'diagram' : $slug;
    }
}
