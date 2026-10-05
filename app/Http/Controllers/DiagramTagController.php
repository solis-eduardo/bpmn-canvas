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
     * The Diagram's edit screen, where its Tags are managed through one
     * multi-select of removable chips (issues #40, #170). Same eligibility as
     * applying a Tag.
     */
    public function edit(string $slug): View
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageTags', $diagram);

        $tagNames = Tag::sortedNames();

        return view('diagrams.edit', ['diagram' => $diagram->load('tags'), 'tagNames' => $tagNames]);
    }

    /**
     * Replaces the Diagram's Tags with the submitted set: chips added in the
     * picker are applied (created on first use), chips removed are detached.
     * An empty submission clears them all. Same eligibility as applying a Tag.
     */
    public function sync(Request $request, string $slug): RedirectResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageTags', $diagram);

        $data = $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'string', 'max:255'],
        ]);

        $names = collect($data['tags'] ?? [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // syncTags() creates the missing Tags itself.
        $diagram->syncTags($names);

        return redirect()->route('diagrams.edit', $diagram->slug);
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
