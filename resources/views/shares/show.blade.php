<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $diagram->title }} — BPMN Studio</title>
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body
    id="viewer"
    data-title="{{ $diagram->title }}"
    data-allow-download="{{ $share->allow_download ? '1' : '0' }}"
>
    <div class="app">
        <header>
            <span class="title">{{ $diagram->title }}</span>
            <span class="spacer"></span>
            @include('partials.language-switcher')
            @if ($share->allow_download)
                <div class="dropdown">
                    <button type="button" id="btn-download">{{ __('messages.download') }} ▾</button>
                    <div class="dropdown-menu">
                        <button type="button" data-export="png">{{ __('messages.png_image') }}</button>
                        <button type="button" data-export="svg">{{ __('messages.svg_image') }}</button>
                        <button type="button" data-export="bpmn">{{ __('messages.bpmn_file') }}</button>
                    </div>
                </div>
            @endif
        </header>
        <main>
            <div id="canvas"></div>
            <div class="message" id="message">{{ __('messages.loading') }}</div>
            <div class="zoom">
                <button type="button" id="zoom-in" title="{{ __('messages.zoom_in') }}">+</button>
                <button type="button" id="zoom-out" title="{{ __('messages.zoom_out') }}">−</button>
                <button type="button" id="zoom-fit" title="{{ __('messages.fit_to_screen') }}">⤢</button>
            </div>
        </main>
    </div>

    <script id="diagram-xml" type="application/xml">{!! $diagramXml !!}</script>
    <script src="{{ asset('vendor/bpmn-js/bpmn-navigated-viewer.production.min.js') }}"></script>
    <script src="{{ asset('assets/common.js') }}"></script>
    <script src="{{ asset('assets/share-viewer.js') }}"></script>
</body>
</html>
