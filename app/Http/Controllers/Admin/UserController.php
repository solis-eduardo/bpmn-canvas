<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    public function index(): View
    {
        $this->authorize('manage-users');

        $users = $this->users->allOrderedByName();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('manage-users');

        return view('admin.users.create');
    }

    public function edit(User $user): View
    {
        $this->authorize('manage-users');

        return view('admin.users.edit', compact('user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        if (filled($data['password'] ?? null) && $user->keycloak_id) {
            throw ValidationException::withMessages([
                'password' => __('messages.keycloak_password_managed_externally'),
            ]);
        }

        if (array_key_exists('is_admin', $data) && ! $data['is_admin'] && $user->is_admin_via_keycloak_claim) {
            abort(403, 'This Administrator role was granted by the Keycloak claim and cannot be revoked here.');
        }

        $user->fill(array_filter(Arr::only($data, ['name', 'password']), filled(...)));

        if (array_key_exists('is_admin', $data)) {
            $user->is_admin = $data['is_admin'];
        }

        $user->save();

        return back();
    }
}
