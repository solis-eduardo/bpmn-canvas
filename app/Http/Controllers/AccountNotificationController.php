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
        $request->validate([
            'email_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update([
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
        ]);

        return redirect()->route('account.notifications.edit')
            ->with('status', __('messages.notification_preferences_saved'));
    }
}
