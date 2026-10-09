<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Tag;
use App\Repositories\DiagramRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiagramTagController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
    ) {}

    /**
     * Replaces the Diagram's Tags with the submitted set (the Editor's info
     * panel, and the edit form through DiagramSettingsController): chips added in the
     * picker are applied (created on first use), chips removed are detached.
     * An empty submission clears them all. Same eligibility as applying a Tag.
     */
    public function sync(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('manageTags', $diagram);

        $data = $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'string', 'max:255'],
        ]);

        $names = Tag::cleanNames($data['tags'] ?? []);

        // syncTags() creates the missing Tags itself.
        $diagram->syncTags($names);

        // The Editor's info panel saves Tags in place and reads the result back.
        if ($request->wantsJson()) {
            return response()->json(['tags' => $names]);
        }

        return redirect()->route('diagrams.show', $diagram->slug);
    }

    /**
     * Applies a Tag to a Diagram: Diagram Owner, Collaborator, or Member of
     * its Workspace (CONTEXT.md, "Tag"; issue #40). The Tag is global and
     * created implicitly on first use — the same row is reused by name
     * across every Diagram.
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

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
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('manageTags', $diagram);

        $diagram->detachTag($tag);

        return redirect()->route('diagrams.edit', $diagram->slug);
    }
}
