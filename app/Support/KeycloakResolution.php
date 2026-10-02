<?php

namespace App\Support;

use App\Models\User;

/**
 * Outcome of tying a Keycloak identity to a local account: an existing
 * account (already linked, or linked now), a new account, or no safe match.
 */
final class KeycloakResolution
{
    private function __construct(
        public readonly ?User $user,
    ) {}

    public static function resolved(User $user): self
    {
        return new self($user);
    }

    public static function rejected(): self
    {
        return new self(null);
    }

    public function isRejected(): bool
    {
        return $this->user === null;
    }
}
