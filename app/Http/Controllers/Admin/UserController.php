<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\ListSort;
use App\Repositories\UserListFilters;
use App\Repositories\UserRepository;
use App\Services\Identity\UserDeactivationService;
use App\Services\Identity\UserSessionRevoker;
use App\Support\KeycloakConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    use RedirectsAfterSave;

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserSessionRevoker $sessionRevoker,
        private readonly UserDeactivationService $deactivation,
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

        return view('admin.users.create');
    }

    public function edit(User $managedUser): View
    {
        $this->authorize('manage-users');

        return view('admin.users.edit', [
            'user' => $managedUser,
            'keycloakConfigured' => KeycloakConfig::isConfigured(),
            'ownedDiagrams' => $this->deactivation->ownedDiagrams($managedUser),
            'ownedWorkspaces' => $this->deactivation->ownedWorkspaces($managedUser),
            'successors' => $this->users->allOrderedByName()->reject(fn (User $candidate) => $candidate->is($managedUser)),
        ]);
    }

    public function deactivate(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorize('manage-users');

        if ($managedUser->is($request->user())) {
            throw ValidationException::withMessages(['deactivated' => __('messages.cannot_deactivate_self')]);
        }

        // The acting Administrator is active (EnsureUserIsActive) and isn't the
        // target, so deactivating another User always leaves one Administrator.
        $this->deactivation->deactivate($managedUser);

        return redirect()->route('admin.users.edit', $managedUser)->with('status', __('messages.user_deactivated'));
    }

    public function reactivate(User $managedUser): RedirectResponse
    {
        $this->authorize('manage-users');

        $this->deactivation->reactivate($managedUser);

        return redirect()->route('admin.users.edit', $managedUser)->with('status', __('messages.user_reactivated'));
    }

    /**
     * Bulk hand-over of every Diagram and Workspace the User owns to an
     * active successor (CONTEXT.md, "Diagram Owner" and "Workspace Owner").
     */
    public function transferOwnership(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'successor_id' => ['required', 'integer', $this->activeUserRule()],
        ], [
            'successor_id.exists' => __('messages.successor_must_be_active'),
        ]);

        $successor = $this->users->findOrFail($data['successor_id']);

        if ($successor->is($managedUser)) {
            throw ValidationException::withMessages(['successor_id' => __('messages.successor_must_be_active')]);
        }

        $this->deactivation->transferOwnership($managedUser, $successor);

        return redirect()->route('admin.users.edit', $managedUser)->with('status', __('messages.ownership_transferred'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Created Users always start with local login; the Administrator can
        // switch the method afterwards, on the edit form (ADR-0015).
        $created = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'login_method' => User::LOGIN_METHOD_LOCAL,
            'password' => Hash::make($data['password']),
        ]);

        return $this->afterSave($request, 'admin.users.index', 'admin.users.edit', [$created]);
    }

    public function update(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'login_method' => ['sometimes', Rule::in($this->allowedLoginMethods())],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $loginMethod = $data['login_method'] ?? $managedUser->login_method;

        $this->assertPasswordRulesForLoginMethod($managedUser, $loginMethod, $data['password'] ?? null);

        $revokesAdmin = array_key_exists('is_admin', $data) && ! $data['is_admin'];

        if ($revokesAdmin && $managedUser->is_admin_via_keycloak_claim) {
            abort(403, __('messages.keycloak_admin_role_not_revocable'));
        }

        $managedUser->fill(array_filter(Arr::only($data, ['name', 'password']), filled(...)));
        $methodChanged = $managedUser->login_method !== $loginMethod;
        $managedUser->login_method = $loginMethod;

        if (array_key_exists('is_admin', $data)) {
            $managedUser->is_admin = $data['is_admin'];
        }

        DB::transaction(function () use ($managedUser, $revokesAdmin): void {
            if ($revokesAdmin && $managedUser->getOriginal('is_admin')) {
                $this->assertAnotherAdministratorRemains($managedUser);
            }

            $managedUser->save();
        });

        return $methodChanged
            ? $this->endSessionsAfterMethodChange($request, $managedUser)
            : $this->afterSave($request, 'admin.users.index', 'admin.users.edit', [$managedUser]);
    }

    /**
     * Locks the other Administrator rows so two concurrent demotions can't both
     * see "another Administrator remains" and leave zero.
     *
     * @throws ValidationException
     */
    private function assertAnotherAdministratorRemains(User $user): void
    {
        if (! $this->users->otherAdministratorExistsLocked($user)) {
            throw ValidationException::withMessages([
                'is_admin' => __('messages.last_administrator_not_removable'),
            ]);
        }
    }

    /**
     * A changed login method invalidates the User's sessions and remember-me
     * token; an Administrator who changed their own method is signed out too.
     */
    private function endSessionsAfterMethodChange(Request $request, User $user): RedirectResponse
    {
        $this->sessionRevoker->revoke($user);

        if (! $user->is($request->user())) {
            return $this->afterSave($request, 'admin.users.index', 'admin.users.edit', [$user]);
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
