<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\CommentThread;
use App\Models\Diagram;
use App\Models\User;
use App\Notifications\DiagramCommentPosted;
use App\Repositories\DiagramRepository;
use App\Support\BpmnElementIds;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Comment Threads of a Diagram (CONTEXT.md, "Comment Thread"; ADR-0024).
 * Authorization (who may open the Diagram) is the controller's; this class
 * holds the rules about Threads: replies reopen, orphans are derived on
 * read, and who is told about a new Comment.
 */
class CommentService
{
    public function __construct(private readonly DiagramRepository $diagrams) {}

    /**
     * Every Thread of $diagram, oldest first, each with its Comments and
     * whether its element is gone from the Diagram's current content.
     *
     * @return list<array<string, mixed>>
     */
    public function list(Diagram $diagram): array
    {
        $threads = $diagram->commentThreads()->with(['comments.author', 'resolvedBy'])->orderBy('id')->get();

        if ($threads->isEmpty()) {
            return [];
        }

        $elementIds = array_flip(BpmnElementIds::extract($this->diagrams->contentOf($diagram)));

        return $threads->map(fn (CommentThread $thread) => $this->present($thread, $elementIds))->all();
    }

    /** Opens a Thread with its first Comment, optionally anchored to an element. */
    public function open(Diagram $diagram, User $author, string $body, ?string $elementId): CommentThread
    {
        $thread = DB::transaction(function () use ($diagram, $author, $body, $elementId) {
            $thread = $diagram->commentThreads()->create(['element_id' => $elementId]);
            $thread->comments()->create(['author_id' => $author->id, 'body' => $body]);

            return $thread;
        });

        $this->notifyParticipants($diagram, $thread, $author);

        return $thread;
    }

    /** Adds a Comment to a Thread; a resolved Thread is reopened by it. */
    public function reply(Diagram $diagram, CommentThread $thread, User $author, string $body): void
    {
        DB::transaction(function () use ($thread, $author, $body) {
            $thread->comments()->create(['author_id' => $author->id, 'body' => $body]);

            if ($thread->isResolved()) {
                $thread->forceFill(['resolved_at' => null, 'resolved_by_id' => null])->save();
            }
        });

        $this->notifyParticipants($diagram, $thread, $author);
    }

    public function setResolved(CommentThread $thread, User $user, bool $resolved): void
    {
        $thread->forceFill($resolved
            ? ['resolved_at' => now(), 'resolved_by_id' => $user->id]
            : ['resolved_at' => null, 'resolved_by_id' => null])->save();
    }

    /**
     * @param  array<string, int>  $elementIds  the current content's element ids, as keys
     * @return array<string, mixed>
     */
    private function present(CommentThread $thread, array $elementIds): array
    {
        return [
            'id' => $thread->id,
            'element_id' => $thread->element_id,
            'orphaned' => $thread->element_id !== null && ! isset($elementIds[$thread->element_id]),
            'resolved' => $thread->isResolved(),
            'resolved_by' => $thread->resolvedBy?->name,
            'resolved_at' => $thread->resolved_at?->toIso8601String(),
            'comments' => $thread->comments->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'author' => $comment->author->name,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /** The Diagram Owner and the Thread's other participants, minus the author. */
    private function notifyParticipants(Diagram $diagram, CommentThread $thread, User $author): void
    {
        $notification = new DiagramCommentPosted($diagram, $thread, $author);

        $this->recipients($diagram, $thread, $author)->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * Active Users only (ADR-0020, through User's default scope) who can
     * still open the Diagram, one entry per User.
     *
     * @return Collection<int, User>
     */
    private function recipients(Diagram $diagram, CommentThread $thread, User $author): Collection
    {
        $ids = $thread->comments()->pluck('author_id')->push($diagram->owner_id)->unique()->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $author->id);

        return User::query()->whereIn('id', $ids)->get()->filter(
            fn (User $user) => $diagram->isVisibleTo($user) && Gate::forUser($user)->allows('view', $diagram),
        )->values();
    }
}
