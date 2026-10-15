<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\Comment;
use App\Models\Diagram;
use App\Repositories\DiagramRepository;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A Diagram's Comment Threads as a JSON resource (CONTEXT.md, "Comment
 * Thread"; ADR-0024). Every verb needs only the right to open the Diagram
 * (`view`): writing one never takes the Lock.
 */
class CommentThreadController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly CommentService $comments,
    ) {}

    public function index(string $slug): JsonResponse
    {
        $diagram = $this->openableDiagram($slug);

        return response()->json(['threads' => $this->comments->list($diagram)]);
    }

    /** Opens a Thread with its first Comment, on the Diagram or on one element. */
    public function store(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->openableDiagram($slug);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.Comment::MAX_BODY_LENGTH],
            'element_id' => ['nullable', 'string', 'max:255'],
        ]);

        $thread = $this->comments->open($diagram, $request->user(), $data['body'], $data['element_id'] ?? null);

        return response()->json(['id' => $thread->id], 201);
    }

    public function reply(Request $request, string $slug, int $thread): JsonResponse
    {
        $diagram = $this->openableDiagram($slug);

        $data = $request->validate(['body' => ['required', 'string', 'max:'.Comment::MAX_BODY_LENGTH]]);

        $this->comments->reply($diagram, $diagram->commentThreads()->findOrFail($thread), $request->user(), $data['body']);

        return response()->json(['id' => $thread], 201);
    }

    /** Resolves or reopens a Thread. */
    public function resolve(Request $request, string $slug, int $thread): JsonResponse
    {
        $diagram = $this->openableDiagram($slug);

        $data = $request->validate(['resolved' => ['required', 'boolean']]);

        $this->comments->setResolved($diagram->commentThreads()->findOrFail($thread), $request->user(), $data['resolved']);

        return response()->json(['id' => $thread, 'resolved' => $data['resolved']]);
    }

    /** The Diagram, for whoever may open it: all a Comment Thread verb needs. */
    private function openableDiagram(string $slug): Diagram
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('view', $diagram);

        return $diagram;
    }
}
