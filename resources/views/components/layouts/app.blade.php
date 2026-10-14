<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' | ' . config('app.name') : config('app.name') }}</title>
    @unless (\App\Support\Ui::standalone())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endunless
    {{ $head ?? '' }}
</head>
<body class="h-full font-sans text-gray-900 antialiased" data-ts-no-results="{{ __('messages.select_no_results') }}" data-ts-create="{{ __('messages.select_create_option') }}"@if (auth()->check() && auth()->user()->timezone === null) data-detect-timezone="{{ route('account.timezone.detect') }}"@endif>
    <div class="{{ ($fluid ?? false) ? 'flex h-screen flex-col overflow-hidden' : 'min-h-full' }}">
        @include('partials.demo-banner')
        {{-- A fluid page keeps the full-width header unless it asks for the standard one. --}}
        @php($fluidHeader = ($fluid ?? false) && ! ($standardHeader ?? false))
        <header class="shrink-0 border-b border-gray-200 bg-white">
            <div class="{{ $fluidHeader ? 'flex w-full flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:flex-nowrap sm:px-6' : 'mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6' }}">
                <a href="{{ \App\Support\Ui::standalone() ? '#/' : url('/home') }}" class="text-lg font-semibold text-gray-900">
                    {{ config('app.name') }}
                </a>

                @php($standalone = \App\Support\Ui::standalone())
                @php($navLink = 'inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900')
                @php($navActive = 'bg-gray-100 text-gray-900')
                {{-- In the standalone page the links are hash routes and standalone/main.js marks the current one. --}}
                <nav class="{{ $fluidHeader ? 'flex w-full min-w-0 flex-wrap items-center gap-1 sm:w-auto sm:flex-1' : 'flex flex-1 items-center gap-1' }}">
                    <a
                        href="{{ $standalone ? '#/home' : url('/home') }}"
                        @if ($standalone) data-nav="home" @endif
                        class="{{ $navLink }} {{ ! $standalone && request()->is('home') ? $navActive : '' }}"
                    >
                        <x-icon name="home" />
                        {{ \App\Support\Ui::text('messages.home') }}
                    </a>
                    <a
                        href="{{ $standalone ? '#/' : route('diagrams.index') }}"
                        @if ($standalone) data-nav="diagrams" @endif
                        class="{{ $navLink }} {{ ! $standalone && request()->routeIs('diagrams.*') ? $navActive : '' }}"
                    >
                        <x-icon name="diagrams" />
                        {{ \App\Support\Ui::text('messages.diagrams') }}
                    </a>
                    <a
                        href="{{ $standalone ? '#/workspaces' : route('workspaces.index') }}"
                        @if ($standalone) data-nav="workspaces" @endif
                        class="{{ $navLink }} {{ ! $standalone && request()->routeIs('workspaces.*') ? $navActive : '' }}"
                    >
                        <x-icon name="workspaces" />
                        {{ \App\Support\Ui::text('messages.workspaces') }}
                    </a>
                    @if ($standalone || (auth()->check() && auth()->user()->is_admin))
                        <a
                            href="{{ $standalone ? '#/users' : route('admin.users.index') }}"
                            @if ($standalone) data-nav="users" @endif
                            class="{{ $navLink }} {{ ! $standalone && request()->routeIs('admin.users.*') ? $navActive : '' }}"
                        >
                            <x-icon name="users" />
                            {{ \App\Support\Ui::text('messages.users') }}
                        </a>
                    @endif
                </nav>

                {{-- Page-specific header controls (e.g. the Editor's back-to-list link
                     and the reserved Version history / Collaborators entry points). --}}
                {{ $headerActions ?? '' }}

                <div class="{{ $fluidHeader ? 'flex flex-wrap items-center gap-3' : 'flex items-center gap-3' }}">
                    @if ($standalone)
                        {{-- Nobody to notify in the standalone page: the bell is only there to keep the header the same. --}}
                        <div class="relative">
                            <button type="button" id="btn-notification-bell" class="relative flex h-8 w-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900"{!! \App\Support\Ui::attr('messages.notifications') !!}>
                                <x-icon name="bell" class="h-5 w-5" />
                            </button>
                        </div>
                        <a href="#/account" data-nav="account" class="{{ $navLink }}">
                            <x-icon name="user-circle" />
                            {{ \App\Support\Ui::text('messages.profile') }}
                        </a>
                        {{ $languageSwitcher ?? '' }}
                    @else
                    @auth
                        @include('partials.notification-bell')
                        <a
                            href="{{ route('account.edit') }}"
                            class="{{ $navLink }} {{ request()->routeIs('account.*') ? $navActive : '' }}"
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
                    @endif
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
