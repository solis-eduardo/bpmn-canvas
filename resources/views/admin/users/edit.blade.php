<x-layouts.app :title="__('messages.edit_user')">
    <x-form.page :title="__('messages.edit_user').': '.$user->name" :back-href="route('admin.users.index')" :back-label="__('messages.back_to_users')">
        <x-form.card :action="route('admin.users.update', $user)" method="PUT">
            <x-form.field name="name" :label="__('messages.name')" value="{{ old('name', $user->name) }}" required />
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
            <x-form.field name="password" type="password" :label="__('messages.set_new_password')" :hint="__('messages.leave_blank_to_keep_password')" autocomplete="new-password" />
            <x-button type="submit">
                <x-icon name="save" />
                {{ __('messages.save') }}
            </x-button>
        </x-form.card>
    </x-form.page>
</x-layouts.app>
