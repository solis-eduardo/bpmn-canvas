<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\User;

class DiagramOwnerPolicy
{
    /**
     * Transfer the Diagram Owner role: the current Diagram Owner or an
     * Administrator (CONTEXT.md, "Diagram Owner"; ADR-0006).
     */
    public function transfer(User $user, Diagram $diagram): bool
    {
        return $diagram->isOwnedBy($user) || $user->is_admin;
    }
}
