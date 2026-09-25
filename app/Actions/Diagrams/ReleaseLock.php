<?php

namespace App\Actions\Diagrams;

use App\Models\Diagram;
use App\Models\User;

/**
 * Releases the Editing Lock: the current holder releases it normally; an
 * Administrator can force the release even without being the holder, "as an
 * alternative to the 30-second mechanism" (CONTEXT.md, "Editing Lock").
 * With no active Lock, it's a successful no-op.
 */
class ReleaseLock
{
    public function __invoke(Diagram $diagram, User $user): bool
    {
        if ($diagram->locked_by === null) {
            return true;
        }

        if ((int) $diagram->locked_by === $user->id) {
            return $diagram->releaseLock($user);
        }

        return $user->is_admin && $diagram->releaseLock();
    }
}
