<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\LockUnavailableException;
use App\Models\Diagram;
use Illuminate\Support\Facades\Gate;

/**
 * The gate every write to a Diagram's own data goes through (its content, its
 * title): the User must be eligible to edit it, and nobody else may hold its
 * Lock (CONTEXT.md, "Lock"). An Administrator who merely opens a Diagram is
 * refused, like in the Editor.
 */
trait AuthorizesDiagramWrites
{
    private function authorizeWrite(Diagram $diagram): void
    {
        abort_unless(Gate::allows('update', $diagram), 403, __('messages.error_cannot_edit_diagram'));

        if (! Gate::allows('lock.write', $diagram)) {
            throw LockUnavailableException::heldByOther($diagram);
        }
    }
}
