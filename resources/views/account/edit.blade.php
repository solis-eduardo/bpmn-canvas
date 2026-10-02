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
            @if ($user->keycloak_id)
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-gray-700">{{ __('messages.login_method') }}</span>
                    <span class="text-sm text-gray-600">
                        {{ __('messages.login_method_keycloak') }} ({{ $user->keycloak_id }})
                    </span>
                </div>
            @endif
            <button
                type="submit"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
            >
                {{ __('messages.save') }}
            </button>
        </form>

        @unless ($user->keycloak_id)
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
                    <button
                        type="submit"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                    >
                        {{ __('messages.change_password') }}
                    </button>
                </form>
            </section>
        @endunless
    </div>
</x-layouts.app>
