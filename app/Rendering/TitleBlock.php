<?php

namespace App\Rendering;

/**
 * The header a PDF export carries for documentation/approval workflows:
 * Diagram title, Version, Diagram Owner and export date, with the labels
 * already translated (the renderer knows no locale).
 */
final readonly class TitleBlock
{
    /**
     * @param  string|null  $version  e.g. "Version 3" or "Approved by the board (3)"; null when the Diagram has no history yet.
     * @param  array{version: string, owner: string, date: string}  $labels
     */
    public function __construct(
        public string $title,
        public ?string $version,
        public string $owner,
        public string $date,
        public array $labels,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'version' => $this->version,
            'owner' => $this->owner,
            'date' => $this->date,
            'labels' => $this->labels,
        ];
    }
}
