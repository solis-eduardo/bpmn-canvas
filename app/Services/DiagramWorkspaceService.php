<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\Workspace;
use App\Repositories\DiagramRepository;

/**
 * Puts a Diagram into a Workspace or takes it out. Who may is
 * DiagramWorkspacePolicy's call.
 */
class DiagramWorkspaceService
{
    public function __construct(private readonly DiagramRepository $diagrams) {}

    /**
     * Moving a Diagram from one Workspace to another is just re-targeting the
     * same column: it belongs to at most one by construction.
     */
    public function assign(Diagram $diagram, Workspace $workspace): void
    {
        $this->diagrams->placeInWorkspace($diagram, $workspace);
    }

    public function remove(Diagram $diagram): void
    {
        $this->diagrams->placeInWorkspace($diagram, null);
    }
}
