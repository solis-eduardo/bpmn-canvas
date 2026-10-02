<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagram->title }} | {{ __('messages.being_edited') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="locked-page">
    @include('partials.language-switcher')

    @auth
        <nav aria-label="{{ __('messages.account_navigation') }}">
            @if (Route::has('account.edit'))
                <a href="{{ route('account.edit') }}">{{ __('messages.profile') }}</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><x-icon name="logout" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.log_out') }}</button>
            </form>
        </nav>
    @endauth

    <p><a href="{{ url('/diagrams') }}">{{ __('messages.diagrams') }}</a></p>

    <h1>{{ $diagram->title }}</h1>

    <p>{!! __('messages.currently_being_edited_by', ['name' => '<strong>'.e($lockedBy->name).'</strong>']) !!}</p>

    @if ($diagram->locked_at)
        <p
            id="lock-since"
            data-locked-at="{{ $diagram->locked_at->toIso8601String() }}"
            title="{{ $diagram->locked_at->toDayDateTimeString() }}"
        >
            {{ __('messages.locked_since', ['time' => $diagram->locked_at->diffForHumans()]) }}
        </p>
    @endif

    <div
        id="lock-request"
        data-request-url="{{ route('diagrams.lock.store', $diagram->slug) }}"
        data-show-url="{{ route('diagrams.show', $diagram->slug) }}"
    >
        <button type="button" id="btn-request-lock"><x-icon name="key" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.request_the_lock') }}</button>
        <p id="lock-request-waiting" hidden>{{ __('messages.lock_request_waiting') }}</p>
    </div>

    <form method="POST" action="{{ route('diagrams.fork', $diagram->slug) }}" class="mt-4 space-y-3">
        @csrf

        <label class="block text-sm font-medium text-gray-700">
            {{ __('messages.workspace_filter_label') }}
            <select name="workspace_id" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">{{ __('messages.no_workspace') }}</option>
                @foreach (auth()->user()->workspaces()->orderBy('name')->get() as $workspace)
                    <option value="{{ $workspace->id }}">{{ $workspace->name }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
            <x-icon name="copy" />
            {{ __('messages.create_a_copy_to_continue') }}
        </button>
    </form>

    <script src="{{ asset('assets/locked.js') }}"></script>
</body>
</html>
