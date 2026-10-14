<?php

namespace App\Support;

/**
 * Request-scoped memo of the access predicates a Diagram answers about a
 * User (isEditableBy, hasApprovedCollaborator, hasManagerRole), shared by
 * every Diagram instance in the request (issue #241).
 *
 * Correctness comes from invalidating on every write that can change an
 * answer, not from per-instance state: AppServiceProvider calls forget()
 * on each save or delete of a Diagram, Collaborator, Member or Workspace
 * (whichever instance makes the change), and the repositories that write
 * through the query builder, which fires no model events, call it
 * themselves. forget() drops the whole memo — access changes are rare next
 * to reads, so coarse invalidation keeps the rule trivially safe.
 *
 * Bound as a scoped singleton, so Octane and queue workers start each
 * request or job empty; RequestHandled also clears it so a long-lived test
 * process behaves like production.
 */
class AccessMemo
{
    /** @var array<string, bool> */
    private array $answers = [];

    /**
     * @param  callable(): bool  $resolve
     */
    public function remember(string $predicate, int|string $diagramId, int $userId, callable $resolve): bool
    {
        return $this->answers["{$predicate}:{$diagramId}:{$userId}"] ??= $resolve();
    }

    public function forget(): void
    {
        $this->answers = [];
    }
}
