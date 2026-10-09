<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Diagram;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Gate;

/**
 * The options of the "new Diagram Owner" picker, offered only to whoever may
 * transfer the role (empty otherwise). The using class provides `$users` and
 * the Controller base provides userOptions().
 *
 * @property-read UserRepository $users
 */
trait ListsOwnerCandidates
{
    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function ownerCandidates(Diagram $diagram): array
    {
        return Gate::allows('diagram-owner.transfer', $diagram)
            ? $this->userOptions($this->users->allOrderedByName(), $diagram->owner)
            : [];
    }
}
