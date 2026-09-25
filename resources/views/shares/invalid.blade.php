<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('messages.invalid_link') }} — BPMN Studio</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body>
    @include('partials.language-switcher')

    <div class="app">
        <main>
            <div class="message">{{ __('messages.invalid_share_link') }}</div>
        </main>
    </div>
</body>
</html>
