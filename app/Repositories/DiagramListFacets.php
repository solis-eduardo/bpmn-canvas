<?php

namespace App\Repositories;

/**
 * The Tag, Diagram Owner and Author facets the Diagram list was narrowed to
 * (issue #150): every Tag a Diagram must carry, and at most one Diagram Owner
 * and one Author (unset meaning "anyone").
 */
final readonly class DiagramListFacets
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public array $tags = [],
        public ?int $ownerId = null,
        public ?int $authorId = null,
    ) {}
}
