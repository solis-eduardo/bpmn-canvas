<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' — ' . config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    <div class="min-h-full">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6">
                <a href="{{ url('/home') }}" class="text-lg font-semibold text-gray-900">
                    {{ config('app.name') }}
                </a>

                <nav class="flex flex-1 items-center gap-1">
                    <a
                        href="{{ url('/home') }}"
                        class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->is('home') ? 'bg-gray-100 text-gray-900' : '' }}"
                    >
                        {{ __('messages.home') }}
                    </a>
                    <a
                        href="{{ route('diagrams.index') }}"
                        class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->routeIs('diagrams.*') ? 'bg-gray-100 text-gray-900' : '' }}"
                    >
                        {{ __('messages.diagrams') }}
                    </a>
                    @can('manage-users')
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 text-gray-900' : '' }}"
                        >
                            {{ __('messages.admin_users') }}
                        </a>
                    @endcan
                </nav>

                {{-- Reserved space for the notification bell (see roadmap). --}}
                <div class="flex items-center gap-3">
                    <div class="h-8 w-8" data-slot="notification-bell" aria-hidden="true"></div>

                    @include('partials.language-switcher')
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
