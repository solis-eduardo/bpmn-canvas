<?php

namespace App\Policies;

use App\Models\Diagram;
use App\Models\User;

/**
 * Who can do what with a Diagram's Lock (CONTEXT.md, "Lock"; ADR-0003,
 * ADR-0009, ADR-0011). Registered as the `lock.*` abilities.
 */
class LockPolicy
{
    /**
     * See who holds the Lock: anyone who may open the Diagram, an
     * Administrator included.
     */
    public function view(User $user, Diagram $diagram): bool
    {
        return $diagram->isOpenableBy($user);
    }

    /**
     * Acquire or request the Lock: actual editors only. An Administrator who
     * merely "sees and opens" a Diagram never holds a Lock.
     */
    public function acquire(User $user, Diagram $diagram): bool
    {
        return $diagram->isEditableBy($user);
    }

    /**
     * Heartbeat: the current holder, nobody else.
     */
    public function renew(User $user, Diagram $diagram): bool
    {
        return $diagram->hasActiveLock() && ! $diagram->isLockedByOther($user);
    }

    /**
     * Transfer the Lock to $newHolder: the current holder or an
     * Administrator, to a User who is eligible to edit. Nothing to transfer
     * when the Lock is free.
     */
    public function transfer(User $user, Diagram $diagram, User $newHolder): bool
    {
        return $diagram->hasActiveLock()
            && $this->canActAsHolder($user, $diagram)
            && $diagram->isEditableBy($newHolder);
    }

    /**
     * Release the Lock: the holder, or an Administrator forcing it. Releasing
     * a free Lock is a harmless no-op, so it is allowed.
     */
    public function release(User $user, Diagram $diagram): bool
    {
        return ! $diagram->hasActiveLock() || $this->canActAsHolder($user, $diagram);
    }

    private function canActAsHolder(User $user, Diagram $diagram): bool
    {
        return ! $diagram->isLockedByOther($user) || $user->is_admin;
    }
}
