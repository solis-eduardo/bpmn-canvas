<?php

namespace App\Http\Controllers;

use App\Actions\Diagrams\GrantOrRequestCollaborator;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use App\Notifications\DiagramAccessRequested;
use App\Notifications\DiagramAccessRequestResolved;
use App\Notifications\DiagramCollaboratorInvited;
use App\Notifications\DiagramCollaboratorRemoved;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiagramCollaboratorController extends Controller
{
    use ResolvesVisibleDiagram;

    /**
     * Author (or Administrator) lists a Diagram's Collaborators/requests.
     */
    public function index(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageCollaborators', $diagram);

        $collaborators = $diagram->collaborators()->with('user')->get()->map(fn (DiagramCollaborator $collaborator) => [
            'user_id' => $collaborator->user_id,
            'name' => $collaborator->user->name,
            'email' => $collaborator->user->email,
            'status' => $collaborator->status,
        ]);

        return response()->json($collaborators);
    }

    /**
     * Direct invite by the Author/Administrator (immediate access, status
     * "approved") or request by the User themselves (status "requested") —
     * see GrantOrRequestCollaborator for the full rule.
     *
     * When targeting by email, returns a generic success response regardless
     * of whether the email matches an existing User, to avoid email enumeration.
     * When targeting by user_id, returns the usual detailed response.
     */
    public function store(Request $request, string $slug, GrantOrRequestCollaborator $grant): JsonResponse|RedirectResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $data = $request->validate([
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email'],
        ]);

        $target = $this->resolveUserFrom($data);

        // When email is used, return a generic success response regardless of
        // whether the user exists (preventing email enumeration). When user_id
        // is used, return the detailed response since user_id lookup can't be
        // probed (validation ensures the ID must exist).
        if (isset($data['email'])) {
            // If user exists by email, still perform the action
            if ($target) {
                $this->grantAndNotify($grant, $diagram, $request->user(), $target);
            }

            // Return the same response regardless of whether the user exists
            return response()->json(['success' => true], 201);
        }

        $collaborator = $this->grantAndNotify($grant, $diagram, $request->user(), $target);

        if (! $request->expectsJson()) {
            return $this->requestRedirect($request);
        }

        return response()->json([
            'user_id' => $collaborator->user_id,
            'status' => $collaborator->status,
        ], 201);
    }

    /**
     * A self-request (never a direct invite — those land as "approved")
     * notifies the Diagram Owner, once: repeating an already pending request
     * doesn't notify again. GrantOrRequestCollaborator only allows a
     * self-request when the requester isn't already able to manage
     * Collaborators, so the Owner is never notifying themselves. A new or
     * newly-approved grant notifies the invited User; an unchanged grant
     * notifies no one.
     */
    private function grantAndNotify(GrantOrRequestCollaborator $grant, Diagram $diagram, User $requester, User $target): DiagramCollaborator
    {
        $wasRequested = DiagramCollaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $target->id)
            ->where('status', DiagramCollaborator::STATUS_REQUESTED)
            ->exists();

        $collaborator = $grant($diagram, $requester, $target);

        if ($collaborator->status === DiagramCollaborator::STATUS_REQUESTED) {
            if (! $wasRequested) {
                $diagram->owner->notify(new DiagramAccessRequested($diagram, $target));
            }
        } elseif ($collaborator->wasRecentlyCreated || $collaborator->wasChanged('status')) {
            $target->notify(new DiagramCollaboratorInvited($diagram));
        }

        return $collaborator;
    }

    private function requestRedirect(Request $request): RedirectResponse
    {
        $discoverySearch = trim((string) $request->query('discover', ''));

        return redirect()->route(
            'diagrams.index',
            $discoverySearch === '' ? [] : ['discover' => $discoverySearch],
        )->with('status', __('messages.access_request_sent'));
    }

    /**
     * Author or Administrator approves a pending request.
     */
    public function update(string $slug, User $user): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageCollaborators', $diagram);

        $collaborator = DiagramCollaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $isPendingRequest = $collaborator->status === DiagramCollaborator::STATUS_REQUESTED;
        $collaborator->update(['status' => DiagramCollaborator::STATUS_APPROVED]);

        if ($isPendingRequest) {
            $user->notify(new DiagramAccessRequestResolved($diagram, approved: true));
        }

        return response()->json([
            'user_id' => $collaborator->user_id,
            'status' => $collaborator->status,
        ]);
    }

    /**
     * Author or Administrator declines a pending request or removes an
     * already-approved Collaborator — in both cases, the row is deleted.
     */
    public function destroy(string $slug, User $user): JsonResponse
    {
        $diagram = $this->resolveDiagram($slug);

        $this->authorize('manageCollaborators', $diagram);

        $collaborator = DiagramCollaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $user->id)
            ->first();

        if ($collaborator) {
            $collaborator->delete();

            if ($collaborator->status === DiagramCollaborator::STATUS_REQUESTED) {
                $user->notify(new DiagramAccessRequestResolved($diagram, approved: false));
            } elseif ($collaborator->status === DiagramCollaborator::STATUS_APPROVED) {
                $user->notify(new DiagramCollaboratorRemoved($diagram));
            }
        }

        return response()->json(['deleted' => true]);
    }
}
