<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'name' => $data['name'],
        ]);

        return redirect()->route('account.edit');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->keycloak_id) {
            abort(403, __('messages.keycloak_password_managed_externally'));
        }

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return redirect()->route('account.edit');
    }
}
