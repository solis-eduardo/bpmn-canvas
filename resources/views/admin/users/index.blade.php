<x-layouts.app :title="__('messages.admin_users')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.admin_users') }}</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                    {{ __('messages.log_out') }}
                </button>
            </form>
        </div>

        {{-- Create User Section --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('messages.create_local_account') }}</h2>

            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            {{ __('messages.name') }}
                        </label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            required
                            value="{{ old('name') }}"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            {{ __('messages.email') }}
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            required
                            value="{{ old('email') }}"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            {{ __('messages.password') }}
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button
                    type="submit"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                >
                    {{ __('messages.create_account') }}
                </button>
            </form>
        </div>

        {{-- Users List Section --}}
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                {{ __('messages.name') }}
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                {{ __('messages.email') }}
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                {{ __('messages.login_method') }}
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                {{ __('messages.administrator') }}
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                {{ __('messages.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $user->name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $user->email }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($user->keycloak_id)
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                                            {{ __('messages.keycloak_sso') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-800">
                                            {{ __('messages.local') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($user->is_admin)
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                                                {{ __('messages.yes') }}
                                            </span>
                                            @if ($user->keycloak_id)
                                                <span class="text-xs text-gray-500">({{ __('messages.from_keycloak') }})</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-500">{{ __('messages.no') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($user->keycloak_id && $user->is_admin)
                                        <span class="text-xs text-gray-500">{{ __('messages.role_from_keycloak') }}</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="inline">
                                            @csrf
                                            @method('PUT')
                                            @if ($user->is_admin)
                                                <input type="hidden" name="is_admin" value="0">
                                                <button
                                                    type="submit"
                                                    class="text-sm font-medium text-red-600 hover:text-red-700"
                                                >
                                                    {{ __('messages.revoke_admin') }}
                                                </button>
                                            @else
                                                <input type="hidden" name="is_admin" value="1">
                                                <button
                                                    type="submit"
                                                    class="text-sm font-medium text-blue-600 hover:text-blue-700"
                                                >
                                                    {{ __('messages.grant_admin') }}
                                                </button>
                                            @endif
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                    {{ __('messages.no_users_yet') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
