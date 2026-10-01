<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Diagram;
use App\Repositories\DiagramRepository;
use App\Repositories\ResolvedDiagram;

/**
 * Shared lookup for Diagram controllers (Collaborators, Tags, Versions,
 * ...): a hidden Diagram (inside a Workspace the User can't see into)
 * answers 404, never 403 (CONTEXT.md, "Workspace"; issue #28), so its
 * existence never leaks through an authorization failure. The using class
 * provides `$diagrams`.
 *
 * @property-read DiagramRepository $diagrams
 */
trait ResolvesVisibleDiagram
{
    private function resolveDiagram(string $slug): Diagram
    {
        return $this->assertVisible($this->diagrams->findBySlugOrFail($slug));
    }

    /**
     * Like resolveDiagram(), but also accepts an old Slug; `viaOldSlug`
     * says whether it was one.
     */
    private function resolveDiagramOrOldSlug(string $slug): ResolvedDiagram
    {
        $resolved = $this->diagrams->findBySlugOrOldSlug($slug) ?? abort(404);

        $this->assertVisible($resolved->diagram);

        return $resolved;
    }

    /**
     * resolveDiagramOrOldSlug() for callers that don't care how the Slug
     * matched.
     */
    private function resolveDiagramAllowingOldSlug(string $slug): Diagram
    {
        return $this->resolveDiagramOrOldSlug($slug)->diagram;
    }

    private function assertVisible(Diagram $diagram): Diagram
    {
        abort_unless($diagram->isVisibleTo(auth()->user()), 404);

        return $diagram;
    }
}
