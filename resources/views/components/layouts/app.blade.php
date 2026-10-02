<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' | ' . config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    <div class="{{ ($fluid ?? false) ? 'flex h-screen flex-col overflow-hidden' : 'min-h-full' }}">
        <header class="shrink-0 border-b border-gray-200 bg-white">
            <div class="{{ ($fluid ?? false) ? 'flex w-full flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:flex-nowrap sm:px-6' : 'mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6' }}">
                <a href="{{ url('/home') }}" class="text-lg font-semibold text-gray-900">
                    {{ config('app.name') }}
                </a>

                <nav class="{{ ($fluid ?? false) ? 'flex w-full min-w-0 flex-wrap items-center gap-1 sm:w-auto sm:flex-1' : 'flex flex-1 items-center gap-1' }}">
                    <a
                        href="{{ url('/home') }}"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->is('home') ? 'bg-gray-100 text-gray-900' : '' }}"
                    >
                        <x-icon name="home" />
                        {{ __('messages.home') }}
                    </a>
                    <a
                        href="{{ route('diagrams.index') }}"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->routeIs('diagrams.*') ? 'bg-gray-100 text-gray-900' : '' }}"
                    >
                        <x-icon name="diagrams" />
                        {{ __('messages.diagrams') }}
                    </a>
                    <a
                        href="{{ route('workspaces.index') }}"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->routeIs('workspaces.*') ? 'bg-gray-100 text-gray-900' : '' }}"
                    >
                        <x-icon name="workspaces" />
                        {{ __('messages.workspaces') }}
                    </a>
                    @if (auth()->check() && auth()->user()->is_admin)
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 text-gray-900' : '' }}"
                        >
                            <x-icon name="users" />
                            {{ __('messages.users') }}
                        </a>
                    @endif
                </nav>

                {{-- Page-specific header controls (e.g. the Editor's back-to-list link
                     and the reserved Version history / Collaborators entry points). --}}
                {{ $headerActions ?? '' }}

                <div class="{{ ($fluid ?? false) ? 'flex flex-wrap items-center gap-3' : 'flex items-center gap-3' }}">
                    @auth
                        @include('partials.notification-bell')
                        <a
                            href="{{ route('account.edit') }}"
                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 {{ request()->routeIs('account.*') ? 'bg-gray-100 text-gray-900' : '' }}"
                        >
                            <x-icon name="user-circle" />
                            {{ __('messages.profile') }}
                        </a>
                    @else
                        <div class="h-8 w-8" data-slot="notification-bell" aria-hidden="true"></div>
                    @endauth

                    @include('partials.language-switcher')

                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                                <x-icon name="logout" />
                                {{ __('messages.log_out') }}
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </header>

        <main class="{{ ($fluid ?? false) ? 'min-h-0 flex-1 overflow-hidden' : 'mx-auto max-w-6xl px-4 py-8 sm:px-6' }}">
            {{ $slot }}
        </main>
    </div>

    @auth
        <script type="module" src="{{ asset('assets/notifications.js') }}"></script>
    @endauth
</body>
</html>
