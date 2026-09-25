<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.log_in') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    <div class="min-h-full flex flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-sm space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ config('app.name') }}</h1>
                <p class="mt-2 text-sm text-gray-600">{{ __('messages.log_in') }}</p>
            </div>

            @if (config('services.keycloak.client_id'))
                <div class="space-y-6">
                    <a
                        href="{{ route('login.keycloak') }}"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        {{ __('messages.login_with_keycloak') }}
                    </a>

                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="bg-gray-50 px-2 text-gray-500">{{ __('messages.or') }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="/login" class="space-y-6">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        {{ __('messages.email') }}
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                    @error('email')
                        <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        {{ __('messages.password') }}
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    {{ __('messages.log_in') }}
                </button>
            </form>

            <div class="text-center">
                @include('partials.language-switcher')
            </div>
        </div>
    </div>
</body>
</html>
