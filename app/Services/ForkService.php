<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;

/**
 * Creates a Fork: a new, independent Diagram with the origin's current
 * content (CONTEXT.md, "Fork"). Whether the User may fork, or place the
 * Fork in $workspace, is ForkPolicy's call.
 */
class ForkService
{
    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly VersionRepository $versions,
    ) {}

    public function create(Diagram $origin, User $forker, ?Workspace $workspace = null): Diagram
    {
        $content = $this->diagrams->contentOf($origin);
        $slug = $this->diagrams->uniqueSlug($origin->title);

        $fork = $this->diagrams->create([
            'title' => $origin->title,
            'slug' => $slug,
            'author_id' => $forker->id,
            'owner_id' => $forker->id,
            'workspace_id' => $workspace?->id,
            'disk_path' => $slug.'/diagram.bpmn',
            'forked_from_id' => $origin->id,
        ], $content);

        // A Fork has its own Version history, starting again at Version 1.
        $this->versions->initialize($fork, $forker);

        return $fork;
    }
}
