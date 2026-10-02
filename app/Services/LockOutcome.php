<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Result of asking for a Lock: either it was acquired, or the request was
 * registered and the caller keeps waiting for the holder to act.
 */
final class LockOutcome
{
    private function __construct(
        public readonly bool $acquired,
        public readonly ?CarbonInterface $requestedAt,
    ) {}

    public static function acquired(): self
    {
        return new self(true, null);
    }

    public static function waiting(CarbonInterface $requestedAt): self
    {
        return new self(false, $requestedAt);
    }

    /**
     * @return array{state: 'acquired'|'waiting', requested_at?: string}
     */
    public function toArray(): array
    {
        return $this->acquired
            ? ['state' => 'acquired']
            : ['state' => 'waiting', 'requested_at' => $this->requestedAt->toIso8601String()];
    }
}
