<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\DiagramListCriteria;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Repositories\VersionRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\CollaboratorService;
use App\Services\LockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
        private readonly UserRepository $users,
    ) {}

    /**
     * Lists Diagrams visible to the current User (CONTEXT.md, "Workspace";
     * issue #28): only Diagrams the User can open (Diagram Owner, approved
     * Collaborator, or Member of its Workspace). An Administrator sees
     * every Diagram. Other out-of-Workspace Diagrams appear in the unified
     * results without Editor access.
     *
     * Accepts an optional "q" search term matching title, Slug (issue #38),
     * or the BPMN content's task/event/gateway/lane/pool names (issue #41).
     * Out-of-Workspace Diagrams a User cannot open are searchable by title
     * only; their results preserve the request-access path without exposing
     * Editor or Diagram content.
     *
     * Also accepts, all combinable with each other and with search (issue
     * #39): "owner"/"collaborator" boolean filters ("I'm
     * the Diagram Owner", "I'm a Collaborator"), a "tag" name filter (issue #40), a "sort" column
     * ("title" or "updated_at", from the table headers) with a "direction" ("asc"/"desc"), and
     * server-side pagination via "page".
     *
     * Filters, search, and sorting are always composed with the visibility
     * rule above, so none of them ever surfaces a hidden Diagram.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->query->has('discover')) {
            return redirect()->route('diagrams.index', DiagramListCriteria::unifiedSearchQuery($request));
        }

        $user = $request->user();
        $criteria = DiagramListCriteria::fromRequest($request);
        $workspaceFilter = (string) $request->query('workspace', 'all');

        $workspaces = $user->is_admin
            ? $this->workspaces->allOrderedByName()
            : $this->workspaces->forUserOrderedByName($user);

        $diagrams = $this->diagrams->searchResultsQuery($user, $criteria)
            ->orderBy($criteria->sort->column, $criteria->sort->direction)
            ->orderBy('diagrams.id')
            ->paginate(self::DIAGRAMS_PER_PAGE)
            ->appends($request->except(['page', 'discovery_page']));

        $discoverableIds = $this->diagrams->discoveryQuery($user, $criteria->search)
            ->whereIn('diagrams.id', $diagrams->getCollection()->modelKeys())
            ->pluck('diagrams.id')
            ->all();
        $discoverableIdSet = array_fill_keys($discoverableIds, true);
        $diagrams->getCollection()->each(
            fn (Diagram $diagram) => $diagram->setAttribute('is_discoverable', isset($discoverableIdSet[$diagram->id]))
        );

        $tagNames = Tag::sortedNames();

        $users = $this->users->allOrderedByName()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all();
        $search = $criteria->search;
        $selectedTags = $criteria->facets->tags;
        $ownerId = $criteria->facets->ownerId;
        $authorId = $criteria->facets->authorId;
        $sort = $criteria->sort;
        $filters = [
            'owner' => $criteria->owner,
            'collaborator' => $criteria->collaborator,
        ];

        return view('diagrams.index', compact(
            'diagrams',
            'search',
            'selectedTags',
            'ownerId',
            'authorId',
            'users',
            'filters',
            'sort',
            'workspaceFilter',
            'workspaces',
            'tagNames',
        ));
    }

    /**
     * Empty create form, reached from the Create button beside the listing
     * title (issue #131); it never carries data from another Diagram.
     */
    public function create(Request $request): View
    {
        return view('diagrams.create', [
            'workspaces' => $this->workspaces->forUserOrderedByName($request->user()),
            'tagNames' => Tag::sortedNames(),
            'collaboratorOptions' => $this->userOptions($this->users->allOrderedByName(), $request->user()),
        ]);
    }

    public function store(Request $request, VersionRepository $versions, CollaboratorService $collaborators): RedirectResponse
    {
        // Only Workspaces the User is a Member of may be chosen (the same rule
        // as DiagramWorkspacePolicy::assign); anything else is a field error.
        $memberWorkspaceIds = $this->workspaces->forUserOrderedByName($request->user())->modelKeys();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'workspace_id' => ['nullable', 'integer', Rule::in($memberWorkspaceIds)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'collaborators' => ['nullable', 'array'],
            'collaborators.*' => ['integer', 'exists:users,id'],
        ]);

        $slug = $this->diagrams->uniqueSlug($data['title']);

        $diagram = $this->diagrams->create([
            'title' => $data['title'],
            'slug' => $slug,
            'author_id' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'workspace_id' => $data['workspace_id'] ?? null,
            'disk_path' => $slug.'/diagram.bpmn',
        ], Diagram::emptyBpmn());

        $versions->initialize($diagram, $request->user());

        // Tags are global and created implicitly on first use (CONTEXT.md, "Tag").
        foreach (array_unique(array_filter(array_map('trim', $data['tags'] ?? []))) as $name) {
            $diagram->attachTag(Tag::findOrCreate($name));
        }

        // The creator is the Diagram Owner, so the picked Users are granted access directly.
        $this->users->findMany(collect($data['collaborators'] ?? [])->unique()->reject(fn ($id) => (int) $id === $request->user()->id)->all())
            ->each(fn (User $user) => $collaborators->grant($diagram, $user));

        // "Save" opens the new Diagram in the Editor, "Save and exit" goes back to the list.
        return $request->input('submit_action') === 'exit'
            ? redirect()->route('diagrams.index')
            : redirect()->route('diagrams.show', $diagram->slug);
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

        $diagram->loadMissing(['author', 'owner', 'tags', 'workspace', 'lockedBy']);
        $diagram->loadCount(['collaborators as approved_collaborators_count' => fn ($query) => $query->where('status', 'approved')]);
        $tagNames = Tag::sortedNames();
        $collaboratorOptions = Gate::allows('manageCollaborators', $diagram)
            ? $this->userOptions($this->users->allOrderedByName(), $diagram->owner)
            : [];

        return view('diagrams.show', compact('diagram', 'tagNames', 'collaboratorOptions'));
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
