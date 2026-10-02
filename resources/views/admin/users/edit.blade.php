<x-layouts.app :title="__('messages.edit_user')">
    <div class="mx-auto max-w-xl">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.edit_user') }}: {{ $user->name }}</h1>
            <x-button :href="route('admin.users.index')" variant="secondary" size="sm">
                <x-icon name="arrow-left" />
                {{ __('messages.back_to_users') }}
            </x-button>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium text-gray-700">{{ __('messages.name') }}</label>
                <x-form.input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    required
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium text-gray-700">{{ __('messages.email') }}</label>
                <input
                    type="email"
                    id="email"
                    value="{{ $user->email }}"
                    readonly
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                >
            </div>
            <div class="flex flex-col gap-1">
                <label for="login_method" class="text-sm font-medium text-gray-700">{{ __('messages.login_method') }}</label>
                <select
                    id="login_method"
                    name="login_method"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    @if ($keycloakConfigured)
                        <option value="local" @selected(old('login_method', $user->login_method) === 'local')>{{ __('messages.login_method_local') }}</option>
                        <option value="keycloak" @selected(old('login_method', $user->login_method) === 'keycloak')>{{ __('messages.login_method_keycloak') }}</option>
                    @else
                        {{-- Without Keycloak the option can't be chosen; a keycloak User's current method is shown but never submitted. --}}
                        @if ($user->usesKeycloak())
                            <option value="" selected disabled>{{ __('messages.login_method_keycloak') }}</option>
                        @endif
                        <option value="local" @selected(! $user->usesKeycloak())>{{ __('messages.login_method_local') }}</option>
                    @endif
                </select>
                @if ($user->keycloak_id)
                    <span class="text-xs text-gray-500">{{ __('messages.login_method_keycloak') }}: {{ $user->keycloak_id }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-1">
                <label for="password" class="text-sm font-medium text-gray-700">{{ __('messages.set_new_password') }}</label>
                <x-form.input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <span class="text-xs text-gray-500">{{ __('messages.leave_blank_to_keep_password') }}</span>
            </div>
            <x-button type="submit">
                <x-icon name="save" />
                {{ __('messages.save') }}
            </x-button>
        </form>
    </div>
</x-layouts.app>
