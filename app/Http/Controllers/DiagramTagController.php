<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Tag;
use App\Repositories\DiagramRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiagramTagController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
    ) {}

    /**
     * The Diagram's edit screen, where its Tags are managed: the current Tags
     * with a remove action, and the picker to apply one (issue #40). Same
     * eligibility as applying a Tag.
     */
    public function edit(string $slug): View
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageTags', $diagram);

        $tagNames = Tag::sortedNames();

        return view('diagrams.edit', ['diagram' => $diagram->load('tags'), 'tagNames' => $tagNames]);
    }

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

        $diagram->attachTag(Tag::findOrCreate(trim($data['name'])));

        return redirect()->route('diagrams.edit', $diagram->slug);
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

        $diagram->detachTag($tag);

        return redirect()->route('diagrams.edit', $diagram->slug);
    }
}
