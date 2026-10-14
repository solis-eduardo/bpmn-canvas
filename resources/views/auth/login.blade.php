<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.log_in') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    @include('partials.demo-banner')
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="flex items-center justify-between gap-4">
                <span class="text-lg font-semibold text-gray-900">{{ config('app.name') }}</span>
                @include('partials.language-switcher')
            </div>

            <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h1 class="text-lg font-medium text-gray-900">{{ __('messages.log_in') }}</h1>

                @if (session('error'))
                    <p role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ session('error') }}
                    </p>
                @endif

                <section aria-label="{{ __('messages.log_in_locally') }}">
                    <form method="POST" action="{{ url('/login') }}" class="mt-4 flex flex-col gap-4">
                        @csrf
                        <div class="flex flex-col gap-1">
                            <label for="email" class="text-xs font-medium text-gray-500">{{ __('messages.email') }}</label>
                            <x-form.input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label for="password" class="text-xs font-medium text-gray-500">{{ __('messages.password') }}</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            >
                        </div>

                        <x-button type="submit">
                            <x-icon name="login" />
                            {{ __('messages.log_in') }}
                        </x-button>
                    </form>
                </section>

                @if (\App\Support\Demo::enabled())
                    <section aria-label="{{ __('messages.demo_accounts') }}" class="mt-4 border-b border-gray-200 pb-4">
                        <h2 class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('messages.demo_accounts') }}</h2>
                        <div class="mt-2 flex flex-col gap-2">
                            @foreach (\App\Support\Demo::ACCOUNTS as $key => $account)
                                <form method="POST" action="{{ route('demo.sign-in', $key) }}">
                                    @csrf
                                    <x-button type="submit" class="w-full justify-between">
                                        <span>{{ __('messages.demo_sign_in_as', ['name' => $account['name']]) }}</span>
                                        <span class="text-xs font-normal opacity-75">{{ __('messages.demo_role_'.$key) }}</span>
                                    </x-button>
                                </form>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($keycloakConfigured)
                    <div class="my-6 flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-gray-400" role="separator">
                        <span class="h-px flex-1 bg-gray-200"></span>
                        {{ __('messages.or') }}
                        <span class="h-px flex-1 bg-gray-200"></span>
                    </div>

                    <a
                        href="{{ route('login.keycloak') }}"
                        class="inline-flex items-center gap-1.5 flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('messages.log_in_with_keycloak') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
