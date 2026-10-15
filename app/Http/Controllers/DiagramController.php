<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDiagramWrites;
use App\Http\Controllers\Concerns\ListsOwnerCandidates;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Models\Share;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\DiagramListCriteria;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Repositories\VersionRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\CollaboratorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiagramController extends Controller
{
    use AuthorizesDiagramWrites;
    use ListsOwnerCandidates;
    use ResolvesVisibleDiagram;

    /**
     * Number of Diagrams shown per page in the list (issue #39).
     */
    private const DIAGRAMS_PER_PAGE = 20;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly WorkspaceRepository $workspaces,
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
            'collaborators.*' => ['integer', $this->activeUserRule()],
        ]);

        $slug = $this->diagrams->uniqueSlug($data['title']);

        $diagram = $this->diagrams->createWithHistory([
            'title' => $data['title'],
            'slug' => $slug,
            'author_id' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'workspace_id' => $data['workspace_id'] ?? null,
            'disk_path' => $slug.'/diagram.bpmn',
        ], Diagram::emptyBpmn(), $versions, $request->user());

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

        // Whoever can open a Diagram without being able to edit it (only an
        // Administrator who isn't Owner, Collaborator or Member: everyone
        // else is turned away by the policy above) gets the read-only Viewer.
        if (Gate::denies('update', $diagram)) {
            return $this->viewer($diagram, __('messages.read_only_diagram_notice'), 'blue', $user);
        }

        // Rendering never takes the Lock: the Editor acquires it with its own
        // POST once loaded (ADR-0003), and refusal sends it to the Viewer. Here
        // the Viewer is only chosen up front when someone else already holds it
        // and is still alive: the Editor takes over an expired Lock.
        if ($diagram->isLockedByOther($user) && ! $diagram->hasExpiredLock()) {
            return $this->viewer($diagram, __('messages.viewer_locked_notice', [
                'name' => $diagram->lockedBy->name,
                'time' => $diagram->locked_at?->diffForHumans() ?? '',
            ]), 'red', $user, requestLock: true);
        }

        $diagram->loadMissing(['author', 'owner', 'tags', 'workspace', 'lockedBy']);
        $diagram->loadCount(['collaborators as approved_collaborators_count' => fn ($query) => $query->where('status', 'approved')->ofActiveUsers()]);
        $tagNames = Tag::sortedNames();
        $collaboratorOptions = Gate::allows('manageCollaborators', $diagram)
            ? $this->userOptions($this->users->allOrderedByName(), $diagram->owner)
            : [];

        $ownerCandidates = $this->ownerCandidates($diagram);

        return view('diagrams.show', [
            'diagram' => $diagram,
            'tagNames' => $tagNames,
            'collaboratorOptions' => $collaboratorOptions,
            'ownerCandidates' => $ownerCandidates,
            ...$this->forkLineage($diagram, $user),
        ]);
    }

    /**
     * Where this Diagram came from and what came from it, limited to what
     * $user can see exist (CONTEXT.md, "Fork"): the origin is named only when
     * visible to them, and so is each Fork listed.
     *
     * @return array{forkedFrom: ?Diagram, forks: Collection<int, Diagram>}
     */
    private function forkLineage(Diagram $diagram, User $user): array
    {
        $origin = $diagram->forkedFrom;

        return [
            'forkedFrom' => $origin?->isVisibleTo($user) ? $origin : null,
            'forks' => $diagram->forks()->visibleTo($user)->orderBy('created_at')->orderBy('id')->get(['id', 'slug', 'title']),
        ];
    }

    private function viewer(Diagram $diagram, string $notice, string $tone, User $user, bool $requestLock = false): View
    {
        $diagram->loadMissing('lockedBy');

        return view('shares.show', [
            'diagram' => $diagram,
            'diagramXml' => $this->diagrams->contentOf($diagram),
            // Comments are written without the Lock, so the Viewer has them too (ADR-0024).
            'commentsDiagram' => $diagram,
            // An Administrator who isn't the Owner opens the Viewer, and still reads the Diagram's Activity (ADR-0025).
            'activityDiagram' => $user->can('viewActivity', $diagram) ? $diagram : null,
            'downloadUrls' => collect(Share::DOWNLOAD_FORMATS)
                ->mapWithKeys(fn (string $format) => [$format => route('diagrams.export', [$diagram->slug, $format])])
                ->all(),
            'backUrl' => route('diagrams.index'),
            'notice' => $notice,
            'noticeTone' => $tone,
            'lockedAt' => $diagram->locked_at,
            'requestLockUrl' => $requestLock ? route('diagrams.lock.request', $diagram->slug) : null,
            // A Fork is available at any time (CONTEXT.md, "Fork"), not only
            // as the way out of someone else's Lock.
            'forkUrl' => route('diagrams.fork', $diagram->slug),
            'showUrl' => route('diagrams.show', $diagram->slug),
            ...$this->forkLineage($diagram, $user),
            'unlockUrl' => $user->is_admin && $diagram->hasActiveLock() ? route('diagrams.lock.destroy', $diagram->slug) : null,
        ]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorizeWrite($diagram);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $diagram = $this->diagrams->rename($diagram, $data['title']);

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
