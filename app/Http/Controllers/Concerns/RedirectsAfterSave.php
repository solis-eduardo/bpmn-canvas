<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The form screens end with two submit buttons (components/form/actions):
 * "Save" stays on the form, "Save and exit" (`submit_action=exit`) returns to
 * the entity's listing. Both targets are fixed routes, never request input.
 */
trait RedirectsAfterSave
{
    /**
     * @param  array<int|string, mixed>  $stayParameters
     */
    protected function afterSave(
        Request $request,
        string $exitRoute,
        string $stayRoute,
        array $stayParameters = [],
        ?string $status = null,
    ): RedirectResponse {
        $redirect = $request->input('submit_action') === 'exit'
            ? redirect()->route($exitRoute)
            : redirect()->route($stayRoute, $stayParameters);

        return $redirect->with('status', $status ?? __('messages.saved'));
    }
}
