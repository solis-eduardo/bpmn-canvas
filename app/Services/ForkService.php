<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;

/**
 * Creates a Fork: a new, independent Diagram with the origin's current
 * content (CONTEXT.md, "Fork"), titled "Copy of <origin title>" in the
 * application's default language. Whether the User may fork, or place the
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
        // The prefix is localized in the application's default language, not
        // the forker's, and resolved once: the stored title never changes.
        $title = __('Copy of :title', ['title' => $origin->title], config('app.default_locale') ?? config('app.locale'));
        $slug = $this->diagrams->uniqueSlug($title);

        $fork = $this->diagrams->create([
            'title' => $title,
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
