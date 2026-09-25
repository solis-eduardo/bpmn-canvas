<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagram->title }} — {{ __('messages.bpmn_editor') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body
    id="editor"
    data-slug="{{ $diagram->slug }}"
    data-title="{{ $diagram->title }}"
    data-content-url="{{ route('diagrams.content', $diagram->slug) }}"
    data-save-url="{{ route('diagrams.save', $diagram->slug) }}"
    data-rename-url="{{ route('diagrams.rename', $diagram->slug) }}"
    data-delete-url="{{ route('diagrams.destroy', $diagram->slug) }}"
    data-index-url="{{ route('diagrams.index') }}"
    data-heartbeat-url="{{ route('diagrams.heartbeat', $diagram->slug) }}"
    data-unlock-url="{{ route('diagrams.unlock', $diagram->slug) }}"
>
    <div class="app">
        <header>
            <span class="brand">{{ __('messages.bpmn_editor') }}</span>
            <span class="credit">
                {{ __('messages.author_label', ['name' => $diagram->author->name]) }}
                {{ __('messages.diagram_owner_label', ['name' => $diagram->owner->name]) }}
            </span>
            <span class="spacer"></span>
            @include('partials.language-switcher')
            <input type="text" id="name" value="{{ $diagram->title }}" autocomplete="off" spellcheck="false">
            <span class="dirty" id="dirty" title="{{ __('messages.unsaved_changes') }}">●</span>
            <button type="button" class="primary" id="btn-save" title="Ctrl+S">{{ __('messages.save') }}</button>
            <button type="button" class="danger" id="btn-delete">{{ __('messages.delete') }}</button>
        </header>
        <main>
            <div id="canvas"></div>
            <div class="zoom">
                <button type="button" id="zoom-in" title="{{ __('messages.zoom_in') }}">+</button>
                <button type="button" id="zoom-out" title="{{ __('messages.zoom_out') }}">−</button>
                <button type="button" id="zoom-fit" title="{{ __('messages.fit_to_screen') }}">⤢</button>
            </div>
        </main>
    </div>

    <script src="{{ asset('vendor/bpmn-js/bpmn-modeler.production.min.js') }}"></script>
    <script src="{{ asset('assets/common.js') }}"></script>
    <script src="{{ asset('assets/editor.js') }}"></script>
</body>
</html>
