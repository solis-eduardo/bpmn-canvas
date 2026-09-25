<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
</head>
<body>
    @include('partials.language-switcher')

    <p>{{ __('messages.hello_user', ['name' => auth()->user()->name]) }}</p>
    <form method="POST" action="/logout">
        @csrf
        <button type="submit">{{ __('messages.log_out') }}</button>
    </form>
</body>
</html>
