<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountNotificationController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.notifications', [
            'emailNotificationsEnabled' => $request->user()->email_notifications_enabled,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update([
            'email_notifications_enabled' => (bool) ($data['email_notifications_enabled'] ?? false),
        ]);

        return redirect()->route('account.notifications.edit')
            ->with('status', __('messages.notification_preferences_saved'));
    }
}
