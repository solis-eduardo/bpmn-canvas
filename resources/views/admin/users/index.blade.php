<x-layouts.app :title="__('messages.users_heading')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.users_heading') }}</h1>
        <x-button :href="route('admin.users.create')">
            <x-icon name="plus" class="h-4 w-4" />
            {{ __('messages.create_user') }}
        </x-button>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 flex flex-wrap items-center gap-3">
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="h-4 w-4 pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                name="name"
                value="{{ $filters->name }}"
                placeholder="{{ __('messages.filter_user_name_or_email') }}"
                aria-label="{{ __('messages.filter_user_name_or_email') }}"
                class="w-full rounded-md border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        </div>

        <select
            name="login_method"
            aria-label="{{ __('messages.login_method') }}"
            class="w-56 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
            <option value="">{{ __('messages.login_method') }}</option>
            <option value="local" @selected($filters->loginMethod === 'local')>{{ __('messages.login_method_local') }}</option>
            @if ($keycloakConfigured)
                <option value="keycloak" @selected($filters->loginMethod === 'keycloak')>{{ __('messages.login_method_keycloak') }}</option>
            @endif
        </select>

        <select
            name="admin"
            aria-label="{{ __('messages.administrator_status') }}"
            class="w-56 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
            <option value="">{{ __('messages.administrator_status') }}</option>
            <option value="1" @selected($filters->admin === true)>{{ __('messages.filter_administrators_only') }}</option>
            <option value="0" @selected($filters->admin === false)>{{ __('messages.not_administrator') }}</option>
        </select>

        <x-button type="submit" variant="secondary">
            <x-icon name="funnel" />
            {{ __('messages.apply_filters') }}
        </x-button>
    </form>

    <div class="mt-8 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm xl:-mx-14">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="whitespace-nowrap bg-gray-50">
                <tr>
                    <x-sortable-th column="name" :sort="$sort" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.name') }}</x-sortable-th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.email') }}</th>
                    <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.login_method') }}</th>
                    <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.administrator_status') }}</th>
                    <x-sortable-th column="updated_at" :sort="$sort" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.diagram_column_modified') }}</x-sortable-th>
                    <th scope="col" class="w-px whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.user_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($users as $user)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-600">
                                {{ $user->usesKeycloak() ? __('messages.login_method_keycloak') : __('messages.login_method_local') }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-center">
                            @if ($user->is_admin)
                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-sm text-green-700">
                                    {{ $user->is_admin_via_keycloak_claim ? __('messages.administrator_via_keycloak') : __('messages.administrator_status') }}
                                </span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-500">
                                    {{ __('messages.not_administrator') }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600"><x-timestamp :at="$user->updated_at" /></td>
                        <td class="w-px whitespace-nowrap px-4 py-3">
                            <div class="flex flex-nowrap items-center gap-3 text-sm">
                                <x-button :href="route('admin.users.edit', $user)" variant="secondary" size="sm">
                                    <x-icon name="pencil" />
                                    {{ __('messages.edit_user') }}
                                </x-button>

                                @if ($user->is_admin && ! $user->adminRoleIsManuallyRevocable())
                                    <span class="text-xs text-gray-400">{{ __('messages.not_revocable_via_keycloak') }}</span>
                                @else
                                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_admin" value="{{ $user->is_admin ? 0 : 1 }}">
                                        <x-button type="submit" :variant="$user->is_admin ? 'destructive' : 'secondary'" size="sm">
                                            <x-icon name="shield-check" />
                                            {{-- Both labels share one grid cell so the button always reserves the width of the longer one. --}}
                                            <span class="inline-grid" data-admin-toggle-label>
                                                <span class="col-start-1 row-start-1">{{ $user->is_admin ? __('messages.revoke_administrator') : __('messages.grant_administrator') }}</span>
                                                <span class="invisible col-start-1 row-start-1" aria-hidden="true">{{ $user->is_admin ? __('messages.grant_administrator') : __('messages.revoke_administrator') }}</span>
                                            </span>
                                        </x-button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">{{ $filters->isActive() ? __('messages.no_users_match_filters') : __('messages.no_users_yet') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</x-layouts.app>
