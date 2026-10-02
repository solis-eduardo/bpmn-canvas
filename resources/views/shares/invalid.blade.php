<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('messages.invalid_link') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm text-center">
            <div class="flex items-center justify-between gap-4">
                <span class="text-lg font-semibold text-gray-900">{{ config('app.name') }}</span>
                @include('partials.language-switcher')
            </div>

            <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h1 class="text-lg font-medium text-gray-900">{{ __('messages.invalid_link') }}</h1>
                <p class="mt-3 text-sm text-gray-500">{{ __('messages.invalid_share_link') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
