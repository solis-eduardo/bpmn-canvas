<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slugger
{
    /**
     * Normaliza um nome livre para um slug em [a-z0-9-]: minúsculas, sem
     * acentos, espaços/símbolos viram "-", sem "-" nas pontas.
     */
    public static function slugify(string $text): string
    {
        $ascii = Str::ascii($text);
        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug === '' ? 'diagrama' : $slug;
    }
}
