<x-layouts.app :title="__('messages.profile')">
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.profile') }}</h1>

        <form method="POST" action="{{ route('account.update') }}" class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
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
            <x-button type="submit">
                <x-icon name="save" />
                {{ __('messages.save') }}
            </x-button>
        </form>

        <section class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.notification_preferences_heading') }}</h2>

            @if (session('status'))
                <p class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700" role="status">
                    {{ session('status') }}
                </p>
            @endif

            <form method="POST" action="{{ route('account.notifications.update') }}" class="mt-4 space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')

                <label for="email_notifications_enabled" class="flex items-start gap-3">
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

                <x-button type="submit">
                    <x-icon name="save" />
                    {{ __('messages.save') }}
                </x-button>
            </form>
        </section>

        @unless ($user->usesKeycloak())
            <section class="mt-8">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.change_password') }}</h2>
                <form method="POST" action="{{ route('account.password.update') }}" class="mt-4 space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-col gap-1">
                        <label for="current_password" class="text-sm font-medium text-gray-700">{{ __('messages.current_password') }}</label>
                        <x-form.input
                            type="password"
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="password" class="text-sm font-medium text-gray-700">{{ __('messages.new_password') }}</label>
                        <x-form.input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="password_confirmation" class="text-sm font-medium text-gray-700">{{ __('messages.confirm_new_password') }}</label>
                        <x-form.input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <x-button type="submit">
                        {{ __('messages.change_password') }}
                    </x-button>
                </form>
            </section>
        @endunless
    </div>
</x-layouts.app>
