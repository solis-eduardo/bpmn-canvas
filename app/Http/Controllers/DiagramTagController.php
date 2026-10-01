<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Tag;
use App\Repositories\DiagramRepository;
use App\Repositories\TagRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiagramTagController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly TagRepository $tags,
    ) {}

    /**
     * Applies a Tag to a Diagram: Diagram Owner, Collaborator, or Member of
     * its Workspace (CONTEXT.md, "Tag"; issue #40). The Tag is global and
     * created implicitly on first use — the same row is reused by name
     * across every Diagram.
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageTags', $diagram);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tag = $this->tags->findOrCreateByName(trim($data['name']));

        $diagram->tags()->syncWithoutDetaching($tag);

        return back();
    }

    /**
     * Removes a Tag from a Diagram: same eligibility as applying one. The
     * Tag itself stays (it's global and may still be used elsewhere), only
     * the Diagram's association with it is removed.
     */
    public function destroy(string $slug, Tag $tag): RedirectResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageTags', $diagram);

        $diagram->tags()->detach($tag);

        return back();
    }
}
