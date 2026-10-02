<?php

namespace App\Repositories;

use App\Models\Diagram;

/**
 * A Diagram found by Slug, and whether the Slug that matched was one of its
 * old ones (so the caller can redirect to the current Slug).
 */
final readonly class ResolvedDiagram
{
    public function __construct(
        public Diagram $diagram,
        public bool $viaOldSlug,
    ) {}
}
