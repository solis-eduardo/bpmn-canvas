<x-layouts.app :title="__('messages.profile')">
    <x-form.page :title="__('messages.profile')" :back-href="route('home')">
        <x-form.card :action="route('account.update')" method="PUT">
            <x-form.field name="name" :label="__('messages.name')" value="{{ old('name', $user->name) }}" required />
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium text-gray-700">{{ __('messages.email') }}</label>
                <input
                    type="email"
                    id="email"
                    value="{{ $user->email }}"
                    disabled
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                >
            </div>
            @if ($user->usesKeycloak())
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-gray-700">{{ __('messages.login_method') }}</span>
                    <span class="text-sm text-gray-600">
                        {{ __('messages.login_method_keycloak') }}@if ($user->keycloak_id) ({{ $user->keycloak_id }})@endif
                    </span>
                </div>
            @endif
            <div class="flex flex-col gap-1">
                <label for="timezone" class="text-sm font-medium text-gray-700">{{ __('messages.timezone') }}</label>
                <select
                    id="timezone"
                    name="timezone"
                    @error('timezone') aria-invalid="true" aria-describedby="timezone-error" @enderror
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="">{{ __('messages.timezone_default', ['timezone' => config('app.timezone')]) }}</option>
                    @foreach (DateTimeZone::listIdentifiers() as $identifier)
                        <option value="{{ $identifier }}" @selected(old('timezone', $user->timezone) === $identifier)>{{ $identifier }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-gray-500">{{ __('messages.timezone_hint') }}</span>
                @error('timezone')
                    <p id="timezone-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <h2 class="pt-2 text-lg font-semibold text-gray-900">{{ __('messages.notification_preferences_heading') }}</h2>
            <label for="email_notifications_enabled" class="flex items-start gap-3">
                <input type="hidden" name="email_notifications_enabled" value="0">
                <input
                    type="checkbox"
                    id="email_notifications_enabled"
                    name="email_notifications_enabled"
                    value="1"
                    @checked(old('email_notifications_enabled', $user->email_notifications_enabled))
                    @error('email_notifications_enabled') aria-invalid="true" aria-describedby="email_notifications_enabled-error" @enderror
                    class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                >
                <span>
                    <span class="block font-medium text-gray-900">{{ __('messages.email_notifications') }}</span>
                    <span class="mt-1 block text-sm text-gray-600">
                        {{ __('messages.email_notifications_description', ['email' => $user->email]) }}
                    </span>
                </span>
            </label>

            @error('email_notifications_enabled')
                <p id="email_notifications_enabled-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            @unless ($user->usesKeycloak())
                <h2 class="pt-2 text-lg font-semibold text-gray-900">{{ __('messages.change_password') }}</h2>
                <x-form.field name="current_password" type="password" :label="__('messages.current_password')" autocomplete="current-password" />
                <x-form.field name="password" type="password" :label="__('messages.new_password')" autocomplete="new-password" />
                <x-form.field name="password_confirmation" type="password" :label="__('messages.confirm_new_password')" autocomplete="new-password" />
            @endunless

            <x-form.actions />
        </x-form.card>
    </x-form.page>
</x-layouts.app>
