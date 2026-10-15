<?php

namespace App\Rendering;

/**
 * Formats the server renders from a Diagram's BPMN (issue #250). `bpmn`
 * itself is not here: it is the stored content, served as is.
 */
enum ExportFormat: string
{
    case Png = 'png';
    case Svg = 'svg';
    case Pdf = 'pdf';

    public function mimeType(): string
    {
        return match ($this) {
            self::Png => 'image/png',
            self::Svg => 'image/svg+xml',
            self::Pdf => 'application/pdf',
        };
    }
}
