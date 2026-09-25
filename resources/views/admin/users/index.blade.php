<x-layouts.app :title="__('messages.users_heading')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.users_heading') }}</h1>
    </div>

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

    <ul class="mt-8 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm">
        @forelse ($users as $user)
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-medium text-gray-900">{{ $user->name }}</p>
                    <p class="text-sm text-gray-500">{{ $user->email }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600">
                        {{ $user->keycloak_id ? __('messages.login_method_keycloak') : __('messages.login_method_local') }}
                    </span>

                    @if ($user->is_admin)
                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-green-700">
                            {{ $user->is_admin_via_keycloak_claim ? __('messages.administrator_via_keycloak') : __('messages.administrator_status') }}
                        </span>
                    @else
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-500">
                            {{ __('messages.not_administrator') }}
                        </span>
                    @endif

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
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('messages.no_users_yet') }}</li>
        @endforelse
    </ul>
</x-layouts.app>
