<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Diagram;

/**
 * Shared lookup for Diagram sub-resource controllers (Collaborators, Tags,
 * ...): a hidden Diagram (inside a Workspace the User can't see into)
 * answers 404, never 403 (CONTEXT.md, "Workspace"; issue #28), so its
 * existence never leaks through an authorization failure.
 */
trait ResolvesVisibleDiagram
{
    private function resolveDiagram(string $slug): Diagram
    {
        return $this->assertVisible(Diagram::where('slug', $slug)->firstOrFail());
    }

    private function assertVisible(Diagram $diagram): Diagram
    {
        abort_unless($diagram->isVisibleTo(auth()->user()), 404);

        return $diagram;
    }
}
