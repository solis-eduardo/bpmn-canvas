<x-layouts.app :title="__('messages.users_heading')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.users_heading') }}</h1>

    <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 flex flex-wrap items-end gap-3">
        @csrf
        <div class="flex flex-col gap-1">
            <label for="name" class="text-xs font-medium text-gray-500">{{ __('messages.name') }}</label>
            <input
                type="text"
                id="name"
                name="name"
                required
                class="min-w-[200px] rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        </div>
        <div class="flex flex-col gap-1">
            <label for="email" class="text-xs font-medium text-gray-500">{{ __('messages.email') }}</label>
            <input
                type="email"
                id="email"
                name="email"
                required
                class="min-w-[220px] rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        </div>
        <div class="flex flex-col gap-1">
            <label for="password" class="text-xs font-medium text-gray-500">{{ __('messages.password') }}</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                class="min-w-[200px] rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        </div>
        <button
            type="submit"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
            {{ __('messages.create_local_account') }}
        </button>
    </form>

    @if ($errors->any())
        <ul class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.name') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.email') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.login_method') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.administrator_status') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.user_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($users as $user)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-600">
                                {{ $user->keycloak_id ? __('messages.login_method_keycloak') : __('messages.login_method_local') }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
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
                        <td class="px-4 py-3">
                            @if ($user->adminRoleIsManuallyRevocable())
                                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_admin" value="0">
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">
                                        {{ __('messages.revoke_administrator') }}
                                    </button>
                                </form>
                            @elseif ($user->is_admin)
                                <span class="text-xs text-gray-400">{{ __('messages.not_revocable_via_keycloak') }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_admin" value="1">
                                    <button type="submit" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                                        {{ __('messages.grant_administrator') }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">{{ __('messages.no_users_yet') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
