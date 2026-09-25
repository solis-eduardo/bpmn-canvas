<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagram->title }} — {{ __('messages.being_edited') }}</title>
</head>
<body>
    @include('partials.language-switcher')

    <p><a href="{{ url('/diagrams') }}">{{ __('messages.diagrams') }}</a></p>

    <h1>{{ $diagram->title }}</h1>

    <p>{!! __('messages.currently_being_edited_by', ['name' => '<strong>'.e($lockedBy->name).'</strong>']) !!}</p>

    <form method="POST" action="{{ route('diagrams.fork', $diagram->slug) }}">
        @csrf
        <button type="submit">{{ __('messages.create_a_copy_to_continue') }}</button>
    </form>
</body>
</html>
