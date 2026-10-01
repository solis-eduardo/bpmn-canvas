<?php

namespace App\Http\Controllers;

use App\Actions\Diagrams\ReleaseLock;
use App\Actions\Diagrams\RequestLock;
use App\Actions\Diagrams\TransferOwnership;
use App\Http\Controllers\Concerns\ResolvesTargetUser;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Repositories\DiagramListCriteria;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Repositories\VersionRepository;
use App\Repositories\WorkspaceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class DiagramController extends Controller
{
    use ResolvesTargetUser;
    use ResolvesVisibleDiagram;

    /**
     * Number of Diagrams shown per page in the list (issue #39).
     */
    private const DIAGRAMS_PER_PAGE = 20;

    /**
     * Sortable columns for the Diagram list (issue #39), mapped from the
     * "sort" query param to the actual column, with each one's default
     * direction when no explicit "direction" is given.
     *
     * @var array<string, string>
     */
    private const SORT_COLUMNS = [
        'title' => 'asc',
        'updated_at' => 'desc',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly DiagramRepository $diagrams,
        private readonly WorkspaceRepository $workspaces,
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
        $discoverySearch = trim((string) $request->query('discover', ''));
        $workspaceFilter = (string) $request->query('workspace', 'all');

        $requestedSort = (string) $request->query('sort', 'title');
        $sort = array_key_exists($requestedSort, self::SORT_COLUMNS) ? $requestedSort : 'title';
        $requestedDirection = $request->query('direction');
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : self::SORT_COLUMNS[$sort];

        $workspaces = $user->is_admin
            ? $this->workspaces->allOrderedByName()
            : $this->workspaces->forUserOrderedByName($user);

        $diagrams = $this->diagrams->visibleListQuery($user, $criteria)
            ->orderBy($sort, $direction)
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
        if ($diagram->isEditableBy($user)) {
            if ($diagram->isLockedByOther($user)) {
                return view('diagrams.locked', [
                    'diagram' => $diagram,
                    'lockedBy' => $diagram->lockedBy,
                ]);
            }

            if (! $diagram->heartbeat($user)) {
                $diagram->acquireLock($user);
            }
        }

        $diagram->loadMissing(['author', 'owner']);

        return view('diagrams.show', compact('diagram'));
    }

    public function content(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('view', $diagram);

        return response()->json([
            'slug' => $diagram->slug,
            'title' => $diagram->title,
            'content' => Storage::disk('diagrams')->get($diagram->disk_path),
        ]);
    }

    public function save(Request $request, string $slug, VersionRepository $versions): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('update', $diagram);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $versions->save($diagram, $request->user(), $data['content']);

        return response()->json(['saved' => true]);
    }

    public function rename(Request $request, string $slug): JsonResponse
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

    /**
     * Transfers the Diagram Owner role to another User: current Diagram
     * Owner or Administrator only (CONTEXT.md, "Diagram Owner"; ADR-0006;
     * issue #14). No acceptance step — takes effect immediately.
     *
     * When targeting by email, returns a generic success response regardless
     * of whether the email matches an existing User, to avoid email enumeration.
     * When targeting by user_id, returns the usual detailed response.
     */
    public function transferOwnership(Request $request, string $slug, TransferOwnership $transfer): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('transferOwnership', $diagram);

        $data = $request->validate([
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email'],
            'give_up_access' => ['sometimes', 'boolean'],
        ]);

        $newOwner = $this->resolveUserFrom($data);

        // When email is used, return a generic success response regardless of
        // whether the user exists (preventing email enumeration). When user_id
        // is used, return the detailed response since user_id lookup can't be
        // probed (validation ensures the ID must exist).
        if (isset($data['email'])) {
            // If user exists by email, still perform the action
            if ($newOwner) {
                $transfer($diagram, $newOwner, (bool) ($data['give_up_access'] ?? false));
            }

            // Return the same response regardless of whether the user exists
            return response()->json(['success' => true]);
        }

        $transfer($diagram, $newOwner, (bool) ($data['give_up_access'] ?? false));

        return response()->json([
            'owner_id' => $newOwner->id,
        ]);
    }

    /**
     * Sends a heartbeat for the current lock holder. Also reports, on the
     * same response, whether someone requested the Lock since the last
     * heartbeat — the Editor piggybacks its "someone wants the Lock" notice
     * on this existing poll rather than opening a second one (issue #9).
     * The pending request is read *before* {@see Diagram::heartbeat()}
     * clears it (a heartbeat counts as the holder responding).
     */
    public function heartbeat(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $diagram->loadMissing('lockRequestedBy');
        $lockRequestedByName = $diagram->lockRequestedBy?->name;

        if (! $diagram->heartbeat($request->user())) {
            return response()->json(['message' => __('messages.lock_not_held')], 403);
        }

        return response()->json([
            'heartbeat' => true,
            'lock_requested_by' => $lockRequestedByName,
        ]);
    }

    public function unlock(Request $request, string $slug, ReleaseLock $releaseLock): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        if (! $releaseLock($diagram, $request->user())) {
            return response()->json(['message' => __('messages.lock_not_held')], 403);
        }

        return response()->json(['unlocked' => true]);
    }

    /**
     * Requests a Lock held by another User. Returns "waiting" until the
     * holder or an Administrator explicitly releases it, or "acquired" if
     * the Lock is already free.
     */
    public function requestLock(Request $request, string $slug, RequestLock $requestLock): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        // 'update', not 'view': requesting a Lock is only meaningful for an
        // actual editor. DiagramPolicy::view()'s admin bypass ("sees and
        // opens") must never let an Administrator who can't save request it.
        $this->authorize('update', $diagram);

        return response()->json($requestLock($diagram, $request->user()));
    }

    public function fork(Request $request, string $slug, VersionRepository $versions): RedirectResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('view', $diagram);

        $data = $request->validate([
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
        ]);

        $workspace = null;
        $workspaceId = $data['workspace_id'] ?? null;

        if ($workspaceId !== null) {
            $workspace = $this->workspaces->findOrFail($workspaceId);
            abort_unless($this->workspaces->hasMember($workspace, $request->user()), 422, __('messages.must_be_workspace_member'));
        }

        // The "diagrams" disk is configured with `throw => false`, so a
        // failed read comes back as null rather than an exception. Catch
        // that explicitly: silently coercing null/false into an empty
        // string would register a Fork with corrupted (empty) content
        // instead of registering nothing.
        $content = Storage::disk('diagrams')->get($diagram->disk_path);

        if (! is_string($content)) {
            throw new RuntimeException("Could not read the Diagram's content to fork it: {$diagram->disk_path}");
        }

        $newSlug = $this->diagrams->uniqueSlug($diagram->title);

        $forked = $this->diagrams->create([
            'title' => $diagram->title,
            'slug' => $newSlug,
            'author_id' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'workspace_id' => $workspace?->id,
            'disk_path' => $newSlug.'/diagram.bpmn',
            'forked_from_id' => $diagram->id,
        ], $content);

        // A Fork has its own, independent Version history, starting again at
        // Version 1 (CONTEXT.md, "Version") — its own Git repository, never
        // sharing history with the origin Diagram's.
        $versions->initialize($forked, $request->user());

        return redirect()->route('diagrams.show', $forked->slug);
    }

    /**
     * Puts the Diagram into a Workspace the Diagram Owner is a Member of
     * (CONTEXT.md, "Diagram Owner"; issue #27). Moving it from one
     * Workspace to another is just re-targeting the same column — a
     * Diagram belongs to at most one Workspace by construction.
     */
    public function putInWorkspace(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('putInWorkspace', $diagram);

        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
        ]);

        $workspace = $this->workspaces->findOrFail($data['workspace_id']);

        abort_unless($this->workspaces->hasMember($workspace, $request->user()), 422, __('messages.must_be_workspace_member'));

        $diagram->update(['workspace_id' => $workspace->id]);

        return response()->json(['workspace_id' => $workspace->id]);
    }

    /**
     * Takes the Diagram out of its Workspace: Diagram Owner or Workspace
     * Owner (CONTEXT.md, "Workspace Owner"; issue #27).
     */
    public function removeFromWorkspace(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('removeFromWorkspace', $diagram);

        $diagram->update(['workspace_id' => null]);

        return response()->json(['workspace_id' => null]);
    }

    public function destroy(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramOrOldSlug($slug)->diagram;

        $this->authorize('delete', $diagram);

        $this->diagrams->remove($diagram);

        return response()->json(['deleted' => true]);
    }
}
