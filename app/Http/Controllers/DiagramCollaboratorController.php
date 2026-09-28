<?php

namespace App\Http\Controllers;

use App\Actions\Diagrams\GrantOrRequestCollaborator;
use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use App\Notifications\DiagramAccessRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagramCollaboratorController extends Controller
{
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
    public function store(Request $request, string $slug, GrantOrRequestCollaborator $grant): JsonResponse
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
                $collaborator = $grant($diagram, $request->user(), $target);
                // Notify on self-request
                if ($collaborator->status === DiagramCollaborator::STATUS_REQUESTED) {
                    $diagram->owner->notify(new DiagramAccessRequested($diagram, $target));
                }
            }

            // Return the same response regardless of whether the user exists
            return response()->json(['success' => true], 201);
        }

        $collaborator = $grant($diagram, $request->user(), $target);

        // A self-request (never a direct invite — those land as "approved")
        // notifies the Diagram Owner. GrantOrRequestCollaborator only
        // allows a self-request when the requester isn't already able to
        // manage Collaborators, so the Owner is never notifying themselves.
        if ($collaborator->status === DiagramCollaborator::STATUS_REQUESTED) {
            $diagram->owner->notify(new DiagramAccessRequested($diagram, $target));
        }

        return response()->json([
            'user_id' => $collaborator->user_id,
            'status' => $collaborator->status,
        ], 201);
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

        $collaborator->update(['status' => DiagramCollaborator::STATUS_APPROVED]);

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

        DiagramCollaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * A hidden Diagram (inside a Workspace the User can't see into) can't be
     * found to request access — 404, never 403 (CONTEXT.md, "Collaborator",
     * "Workspace"; issue #28). An invitation from the Diagram Owner or an
     * Administrator still reaches it, since both can already see it.
     */
    private function resolveDiagram(string $slug): Diagram
    {
        $diagram = Diagram::where('slug', $slug)->firstOrFail();

        abort_unless($diagram->isVisibleTo(auth()->user()), 404);

        return $diagram;
    }
}
