<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDiagramWrites;
use App\Http\Controllers\Concerns\ListsOwnerCandidates;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Models\Tag;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Services\DiagramOwnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The Diagram's edit form (issue #195): Title, Diagram Owner and Tags on one
 * screen. Each field answers to the same rule as its own endpoint (rename,
 * `diagram-owner.transfer`, Tags), so the form shows a field only to whoever
 * may change it and refuses a submission that touches one they may not.
 * The Editor's info panel reaches the same actions through the JSON endpoints.
 */
class DiagramSettingsController extends Controller
{
    use AuthorizesDiagramWrites;
    use ListsOwnerCandidates;
    use RedirectsAfterSave;
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly UserRepository $users,
        private readonly DiagramOwnerService $owners,
    ) {}

    public function edit(string $slug): View
    {
        $diagram = $this->resolveDiagram($slug);

        abort_unless(Gate::allows('update', $diagram) || Gate::allows('diagram-owner.transfer', $diagram), 403);

        return view('diagrams.edit', [
            'diagram' => $diagram->load('tags'),
            'tagNames' => Tag::sortedNames(),
            'ownerCandidates' => $this->ownerCandidates($diagram),
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'give_up_access' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'string', 'max:255'],
        ]);

        // Authorize every requested change before applying any of them.
        $changes = $this->requestedChanges($request, $diagram, $data);
        $this->authorizeChanges($diagram, $changes);
        $diagram = $this->applyChanges($diagram, $changes, $data);

        // Giving up access leaves nothing to come back to on this screen.
        $user = $request->user();
        $keepsAccess = $user->is_admin || $diagram->refresh()->isEditableBy($user);

        return $this->afterSave(
            $request,
            'diagrams.index',
            $keepsAccess ? 'diagrams.edit' : 'diagrams.index',
            $keepsAccess ? [$diagram->slug] : [],
        );
    }

    /**
     * Which parts of the submission ask for a change.
     *
     * @param  array<string, mixed>  $data
     * @return array{rename: bool, transfer: bool, tags: bool}
     */
    private function requestedChanges(Request $request, Diagram $diagram, array $data): array
    {
        return [
            'rename' => isset($data['title']) && $data['title'] !== $diagram->title,
            'transfer' => ! empty($data['user_id']),
            // A form without the Tags field (it isn't shown to everyone) leaves them alone;
            // an editor's empty selection submits nothing and clears them.
            'tags' => $request->has('tags') || (isset($data['title']) && Gate::allows('manageTags', $diagram)),
        ];
    }

    /**
     * @param  array{rename: bool, transfer: bool, tags: bool}  $changes
     */
    private function authorizeChanges(Diagram $diagram, array $changes): void
    {
        if ($changes['rename']) {
            $this->authorizeWrite($diagram);
        }
        if ($changes['transfer']) {
            $this->authorize('diagram-owner.transfer', $diagram);
        }
        if ($changes['tags']) {
            $this->authorize('manageTags', $diagram);
        }
    }

    /**
     * @param  array{rename: bool, transfer: bool, tags: bool}  $changes
     * @param  array<string, mixed>  $data
     */
    private function applyChanges(Diagram $diagram, array $changes, array $data): Diagram
    {
        if ($changes['rename']) {
            $diagram = $this->diagrams->rename($diagram, $data['title']);
        }
        if ($changes['transfer']) {
            $this->owners->transfer($diagram, $this->users->findOrFail($data['user_id']), (bool) ($data['give_up_access'] ?? false));
        }
        if ($changes['tags']) {
            // syncTags() creates the missing Tags itself.
            $diagram->syncTags(Tag::cleanNames($data['tags'] ?? []));
        }

        return $diagram;
    }
}
