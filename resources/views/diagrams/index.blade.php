<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('messages.diagrams') }} — {{ config('app.name') }}</title>
</head>
<body>
    @include('partials.language-switcher')

    <p><a href="{{ url('/home') }}">{{ __('messages.home') }}</a></p>

    <h1>{{ __('messages.diagrams') }}</h1>

    <form method="POST" action="{{ route('diagrams.store') }}">
        @csrf
        <input type="text" name="title" placeholder="{{ __('messages.diagram_name') }}" required>
        <button type="submit">{{ __('messages.create_diagram') }}</button>
    </form>

    <ul>
        @forelse ($diagrams as $diagram)
            <li>
                <a href="{{ route('diagrams.show', $diagram->slug) }}">{{ $diagram->title }}</a>
                {{ __('messages.author_label', ['name' => $diagram->author->name]) }}
                @if ($diagram->hasActiveLock())
                    {{ __('messages.being_edited_by', ['name' => $diagram->lockedBy->name]) }}
                @endif
            </li>
        @empty
            <li>{{ __('messages.no_diagrams_yet') }}</li>
        @endforelse
    </ul>
</body>
</html>
