<?php

namespace App\Support;

/**
 * Request-scoped memo of the access predicates a Diagram answers about a
 * User (AccessPredicate), shared by every Diagram instance in the request
 * (issue #241). The alternative the issue offers, loading the User's
 * relation to the Diagram once and deriving every check from it, was not
 * taken: the predicates stay plain queries and the memo only removes the
 * repeats.
 *
 * Correctness comes from invalidating on every write that can change an
 * answer, not from per-instance state. AccessMemoServiceProvider calls
 * forget() when a Diagram's owner or Workspace changes or the Diagram is
 * deleted, on every save or delete of a Collaborator or Member, on the
 * delete of a Workspace or User (their rows cascade away in the database),
 * and when a database transaction rolls back (a rolled-back write must not
 * outlive an answer read inside it). Writes that fire no model event must
 * call forget() themselves: MemberRepository's builder deletes and
 * RestoreRepository do. saveQuietly(), withoutEvents() and raw writes to
 * these tables are therefore forbidden unless they forget() too;
 * AccessMemoWriteGuardTest fails on the usual ways of doing that.
 * forget() drops the whole memo, since access changes are rare next to
 * reads.
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
    public function remember(AccessPredicate $predicate, int $diagramId, int $userId, callable $resolve): bool
    {
        return $this->answers["{$predicate->value}:{$diagramId}:{$userId}"] ??= $resolve();
    }

    public function forget(): void
    {
        $this->answers = [];
    }
}
