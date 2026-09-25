<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('messages.log_in') }} — {{ config('app.name') }}</title>
</head>
<body>
    @include('partials.language-switcher')

    <form method="POST" action="/login">
        @csrf
        <label>
            {{ __('messages.email') }}
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        @error('email')
            <p role="alert">{{ $message }}</p>
        @enderror

        <label>
            {{ __('messages.password') }}
            <input type="password" name="password" required>
        </label>

        <button type="submit">{{ __('messages.log_in') }}</button>
    </form>
</body>
</html>
