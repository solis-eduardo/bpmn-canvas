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
 * application's default language. When the forker brings $content (the edits
 * open in their Editor, not yet saved), the Fork starts from that instead of
 * the origin's saved content (issue #243); when it names a $version, the Fork
 * starts from that Version's content instead (issue #248), and $copyTags
 * gives it the origin's Tags. Whether the User may fork, or place
 * the Fork in $workspace, is ForkPolicy's call.
 */
class ForkService
{
    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly VersionRepository $versions,
    ) {}

    public function create(Diagram $origin, User $forker, ?Workspace $workspace = null, ?string $content = null, bool $copyTags = false, ?string $version = null): Diagram
    {
        $content ??= $version !== null
            ? $this->versions->get($origin, $version)
            : $this->diagrams->contentOf($origin);
        // The prefix is localized in the application's default language, not
        // the forker's, and resolved once: the stored title never changes.
        $title = __('Copy of :title', ['title' => $origin->title], config('app.default_locale') ?? config('app.locale'));
        $slug = $this->diagrams->uniqueSlug($title);

        // A Fork has its own Version history, starting again at Version 1.
        $fork = $this->diagrams->createWithHistory([
            'title' => $title,
            'slug' => $slug,
            'author_id' => $forker->id,
            'owner_id' => $forker->id,
            'workspace_id' => $workspace?->id,
            'disk_path' => $slug.'/diagram.bpmn',
            'forked_from_id' => $origin->id,
        ], $content, $this->versions, $forker);

        if ($copyTags) {
            $fork->attachTags($origin->tags->all());
        }

        return $fork;
    }
}
