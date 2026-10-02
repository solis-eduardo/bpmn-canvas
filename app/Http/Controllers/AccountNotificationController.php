<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsAfterSave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountNotificationController extends Controller
{
    use RedirectsAfterSave;

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'email_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update([
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
        ]);

        return $this->afterSave($request, 'home', 'account.edit', status: __('messages.notification_preferences_saved'));
    }
}
