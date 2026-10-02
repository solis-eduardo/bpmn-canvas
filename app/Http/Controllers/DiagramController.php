<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Repositories\DiagramListCriteria;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\LockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DiagramController extends Controller
{
    use ResolvesVisibleDiagram;

    /**
     * Number of Diagrams shown per page in the list (issue #39).
     */
    private const DIAGRAMS_PER_PAGE = 20;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly WorkspaceRepository $workspaces,
        private readonly LockService $locks,
    ) {}

    /**
     * Lists Diagrams visible to the current User (CONTEXT.md, "Workspace";
     * issue #28): only Diagrams the User can open (Diagram Owner, approved
     * Collaborator, or Member of its Workspace). An Administrator sees
     * every Diagram. Other out-of-Workspace Diagrams are listed separately
     * as searchable discovery results without Editor access.
     *
     * Accepts an optional "q" search term matching title, Slug (issue #38),
     * or the BPMN content's task/event/gateway/lane/pool names (issue #41).
     *
     * Also accepts, all combinable with each other and with search (issue
     * #39): "owner"/"collaborator"/"locked"/"recent" boolean filters ("I'm
     * the Diagram Owner", "I'm a Collaborator", "locked right now",
     * "recently modified"), a "tag" name filter (issue #40), a "sort" column
     * ("title" or "updated_at") with a "direction" ("asc"/"desc"), and
     * server-side pagination via "page".
     *
     * Filters, search, and sorting are always composed with the visibility
     * rule above, so none of them ever surfaces a hidden Diagram.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $criteria = DiagramListCriteria::fromRequest($request);
        $discoverySearch = DiagramListCriteria::discoverSearch($request);
        $workspaceFilter = (string) $request->query('workspace', 'all');

        $workspaces = $user->is_admin
            ? $this->workspaces->allOrderedByName()
            : $this->workspaces->forUserOrderedByName($user);

        $diagrams = $this->diagrams->visibleListQuery($user, $criteria)
            ->orderBy($criteria->sort->column, $criteria->sort->direction)
            ->orderBy('id')
            ->paginate(self::DIAGRAMS_PER_PAGE)
            ->appends($request->query());

        $discoveryDiagrams = $this->diagrams->discoveryQuery($user, $discoverySearch)
            ->orderBy('title')
            ->orderBy('id')
            ->simplePaginate(self::DIAGRAMS_PER_PAGE, ['*'], 'discovery_page')
            ->appends($request->except(['page', 'discovery_page']));

        $search = $criteria->search;
        $tag = $criteria->tag;
        $sort = $criteria->sort->column;
        $direction = $criteria->sort->direction;
        $filters = [
            'owner' => $criteria->owner,
            'collaborator' => $criteria->collaborator,
            'locked' => $criteria->locked,
            'recent' => $criteria->recent,
        ];

        return view('diagrams.index', compact(
            'diagrams',
            'discoveryDiagrams',
            'search',
            'discoverySearch',
            'tag',
            'filters',
            'sort',
            'direction',
            'workspaceFilter',
            'workspaces',
        ));
    }

    public function store(Request $request, VersionRepository $versions): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $slug = $this->diagrams->uniqueSlug($data['title']);

        $diagram = $this->diagrams->create([
            'title' => $data['title'],
            'slug' => $slug,
            'author_id' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'disk_path' => $slug.'/diagram.bpmn',
        ], Diagram::emptyBpmn());

        $versions->initialize($diagram, $request->user());

        return redirect()->route('diagrams.show', $diagram->slug);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $resolved = $this->resolveDiagramOrOldSlug($slug);
        $diagram = $resolved->diagram;

        if ($resolved->viaOldSlug) {
            return redirect()->route('diagrams.show', $diagram->slug);
        }

        $this->authorize('view', $diagram);

        $user = $request->user();

        // The Editing Lock only concerns actual editors (Diagram Owner,
        // Collaborator, Member) — an Administrator who can merely "see and
        // open" the Diagram (DiagramPolicy::view()'s admin bypass) but
        // can't save must never acquire, hold, or be blocked by it.
        if (Gate::allows('lock.acquire', $diagram) && ! $this->locks->enter($diagram, $user)) {
            return view('diagrams.locked', [
                'diagram' => $diagram,
                'lockedBy' => $diagram->lockedBy,
            ]);
        }

        $diagram->loadMissing(['author', 'owner']);

        return view('diagrams.show', compact('diagram'));
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $newSlug = $this->diagrams->uniqueSlug($data['title'], $diagram->id);

        $diagram = $this->diagrams->move($diagram, $data['title'], $newSlug);

        return response()->json([
            'slug' => $diagram->slug,
            'title' => $diagram->title,
        ]);
    }

    public function destroy(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('delete', $diagram);

        $this->diagrams->remove($diagram);

        return response()->json(['deleted' => true]);
    }
}
