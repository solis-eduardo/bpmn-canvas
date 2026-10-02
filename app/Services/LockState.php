<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Snapshot of a Diagram's Lock: who holds it and who, if anyone, is waiting
 * for it (CONTEXT.md, "Lock").
 */
final class LockState
{
    public function __construct(
        public readonly ?User $holder,
        public readonly ?User $requester,
        public readonly ?CarbonInterface $requestedAt,
    ) {}

    public function isHeld(): bool
    {
        return $this->holder !== null;
    }

    public function isHeldBy(User $user): bool
    {
        return $this->holder?->id === $user->id;
    }
}
