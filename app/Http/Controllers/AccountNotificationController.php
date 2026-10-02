<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountNotificationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'email_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update([
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
        ]);

        return redirect()->route('account.edit')
            ->with('status', __('messages.notification_preferences_saved'));
    }
}
