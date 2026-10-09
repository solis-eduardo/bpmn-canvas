<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Snapshot of a Diagram's Lock: who holds it and who, if anyone, is waiting
 * for it, and whether the holder has an unsaved draft on the server
 * (CONTEXT.md, "Lock").
 */
final class LockState
{
    public function __construct(
        public readonly ?User $holder,
        public readonly ?User $requester,
        public readonly ?CarbonInterface $requestedAt,
        public readonly bool $hasDraft = false,
    ) {}

    public function isHeld(): bool
    {
        return $this->holder !== null;
    }

    public function isHeldBy(User $user): bool
    {
        return $this->holder?->id === $user->id;
    }

    /**
     * @return array{held: bool, holder: array{id: int, name: string}|null, requested_by: string|null, requested_at: string|null, has_draft: bool}
     */
    public function toArray(): array
    {
        return [
            'held' => $this->isHeld(),
            'holder' => $this->holder ? ['id' => $this->holder->id, 'name' => $this->holder->name] : null,
            'requested_by' => $this->requester?->name,
            'requested_at' => $this->requestedAt?->toIso8601String(),
            'has_draft' => $this->hasDraft,
        ];
    }
}
