<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Demo;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    use RedirectsAfterSave;

    public function __construct(
        private readonly UserRepository $users,
    ) {}

    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // One form: name, email notification preference and, for local
        // accounts, an optional password change (only when a new one is typed).
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email_notifications_enabled' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'nullable', Rule::in(DateTimeZone::listIdentifiers())],
        ];

        if (! $user->usesKeycloak() && ! $this->passwordLocked($user)) {
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];
            $rules['current_password'] = ['nullable', 'required_with:password', 'current_password'];
        }

        $data = $request->validate($rules);

        $attributes = ['name' => $data['name']];

        if ($request->has('email_notifications_enabled')) {
            $attributes['email_notifications_enabled'] = $request->boolean('email_notifications_enabled');
        }

        if ($request->has('timezone')) {
            $attributes['timezone'] = $data['timezone'];
        }

        $changesPassword = ! $user->usesKeycloak() && ! $this->passwordLocked($user) && filled($data['password'] ?? null);

        if ($changesPassword) {
            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);

        if ($changesPassword) {
            $this->users->invalidateOtherSessions($user, $request->session()->getId());
        }

        return $this->afterSave($request, 'home', 'account.edit');
    }

    /**
     * Fills the User's timezone from the browser on first access. Never
     * overwrites a timezone the User already has.
     */
    public function detectTimezone(Request $request): Response
    {
        $data = $request->validate(['timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())]]);

        $user = $request->user();

        if ($user->timezone === null) {
            $user->update(['timezone' => $data['timezone']]);
        }

        return response()->noContent();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->usesKeycloak()) {
            abort(403, __('messages.keycloak_password_managed_externally'));
        }

        abort_if($this->passwordLocked($user), 403, __('messages.demo_password_locked'));

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $data['password']]);

        $this->users->invalidateOtherSessions($user, $request->session()->getId());

        return $this->afterSave($request, 'home', 'account.edit');
    }

    /**
     * The demo's shared accounts keep their known password.
     */
    private function passwordLocked(User $user): bool
    {
        return Demo::enabled() && Demo::isAccount($user);
    }
}
