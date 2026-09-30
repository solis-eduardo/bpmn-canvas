<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagram->title }} — {{ __('messages.being_edited') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body>
    @include('partials.language-switcher')

    @auth
        <nav aria-label="{{ __('messages.account_navigation') }}">
            @if (Route::has('account.edit'))
                <a href="{{ route('account.edit') }}">{{ __('messages.profile') }}</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">{{ __('messages.log_out') }}</button>
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
        data-request-url="{{ route('diagrams.lock.request', $diagram->slug) }}"
        data-show-url="{{ route('diagrams.show', $diagram->slug) }}"
    >
        <button type="button" id="btn-request-lock">{{ __('messages.request_the_lock') }}</button>
        <p id="lock-request-waiting" hidden>{{ __('messages.lock_request_waiting') }}</p>
    </div>

    <form method="POST" action="{{ route('diagrams.fork', $diagram->slug) }}">
        @csrf
        <button type="submit">{{ __('messages.create_a_copy_to_continue') }}</button>
    </form>

    <script src="{{ asset('assets/locked.js') }}"></script>
</body>
</html>
