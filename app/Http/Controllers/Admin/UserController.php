<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\ListSort;
use App\Repositories\UserListFilters;
use App\Repositories\UserRepository;
use App\Services\Identity\UserSessionRevoker;
use App\Support\KeycloakConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserSessionRevoker $sessionRevoker,
    ) {}

    /**
     * Paginated, and narrowed by the "name", "login_method" and "admin"
     * query filters (issue #151), which are kept on the pagination links.
     */
    public function index(Request $request): View
    {
        $this->authorize('manage-users');

        $filters = UserListFilters::fromRequest($request);
        $sort = ListSort::fromRequest($request, UserRepository::SORT_COLUMNS);
        $users = $this->users->search($filters, $sort)->appends($request->query());

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'sort' => $sort,
            'keycloakConfigured' => KeycloakConfig::isConfigured(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-users');

        return view('admin.users.create', ['keycloakConfigured' => KeycloakConfig::isConfigured()]);
    }

    public function edit(User $user): View
    {
        $this->authorize('manage-users');

        return view('admin.users.edit', ['user' => $user, 'keycloakConfigured' => KeycloakConfig::isConfigured()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'login_method' => ['sometimes', Rule::in($this->allowedLoginMethods())],
            'password' => [
                Rule::requiredIf(fn () => ($request->input('login_method') ?? User::LOGIN_METHOD_LOCAL) === User::LOGIN_METHOD_LOCAL),
                'nullable', 'string', 'min:8',
            ],
        ]);

        // A `keycloak` User may have no password; it stays unlinked until the
        // first verified Keycloak login (ADR-0015).
        $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'login_method' => $data['login_method'] ?? User::LOGIN_METHOD_LOCAL,
            'password' => filled($data['password'] ?? null) ? Hash::make($data['password']) : null,
        ]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'login_method' => ['sometimes', Rule::in($this->allowedLoginMethods())],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $loginMethod = $data['login_method'] ?? $user->login_method;

        $this->assertPasswordRulesForLoginMethod($user, $loginMethod, $data['password'] ?? null);

        if (array_key_exists('is_admin', $data) && ! $data['is_admin'] && $user->is_admin_via_keycloak_claim) {
            abort(403, 'This Administrator role was granted by the Keycloak claim and cannot be revoked here.');
        }

        $user->fill(array_filter(Arr::only($data, ['name', 'password']), filled(...)));
        $methodChanged = $user->login_method !== $loginMethod;
        $user->login_method = $loginMethod;

        if (array_key_exists('is_admin', $data)) {
            $user->is_admin = $data['is_admin'];
        }

        $user->save();

        return $methodChanged ? $this->endSessionsAfterMethodChange($request, $user) : back();
    }

    /**
     * A changed login method invalidates the User's sessions and remember-me
     * token; an Administrator who changed their own method is signed out too.
     */
    private function endSessionsAfterMethodChange(Request $request, User $user): RedirectResponse
    {
        $this->sessionRevoker->revoke($user);

        if (! $user->is($request->user())) {
            return back();
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * @throws ValidationException
     */
    private function assertPasswordRulesForLoginMethod(User $user, string $loginMethod, ?string $password): void
    {
        if (filled($password) && $loginMethod === User::LOGIN_METHOD_KEYCLOAK) {
            throw ValidationException::withMessages([
                'password' => __('messages.keycloak_password_managed_externally'),
            ]);
        }

        // Switching back to `local` restores the kept password; an account
        // that never had one needs a new password in the same action.
        $switchingToLocal = $loginMethod === User::LOGIN_METHOD_LOCAL
            && $user->login_method === User::LOGIN_METHOD_KEYCLOAK;

        if ($switchingToLocal && blank($password) && blank($user->password)) {
            throw ValidationException::withMessages([
                'password' => __('messages.password_required_to_switch_to_local'),
            ]);
        }
    }

    /**
     * Login methods the Administrator may assign: `keycloak` only exists as a
     * choice while Keycloak is configured (ADR-0015).
     *
     * @return list<string>
     */
    private function allowedLoginMethods(): array
    {
        return KeycloakConfig::isConfigured()
            ? [User::LOGIN_METHOD_LOCAL, User::LOGIN_METHOD_KEYCLOAK]
            : [User::LOGIN_METHOD_LOCAL];
    }
}
