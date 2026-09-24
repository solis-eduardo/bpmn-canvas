<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $diagrama->title }} — BPMN Studio</title>
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body
    id="viewer"
    data-title="{{ $diagrama->title }}"
    data-allow-download="{{ $compartilhamento->allow_download ? '1' : '0' }}"
>
    <div class="app">
        <header>
            <span class="title">{{ $diagrama->title }}</span>
            <span class="spacer"></span>
            @if ($compartilhamento->allow_download)
                <div class="dropdown">
                    <button type="button" id="btn-download">Baixar ▾</button>
                    <div class="dropdown-menu">
                        <button type="button" data-export="png">Imagem PNG</button>
                        <button type="button" data-export="svg">Imagem SVG</button>
                        <button type="button" data-export="bpmn">Arquivo BPMN (.bpmn)</button>
                    </div>
                </div>
            @endif
        </header>
        <main>
            <div id="canvas"></div>
            <div class="message" id="message">Carregando…</div>
            <div class="zoom">
                <button type="button" id="zoom-in" title="Aproximar">+</button>
                <button type="button" id="zoom-out" title="Afastar">−</button>
                <button type="button" id="zoom-fit" title="Ajustar à tela">⤢</button>
            </div>
        </main>
    </div>

    <script id="diagram-xml" type="application/xml">{!! $diagramaXml !!}</script>
    <script src="{{ asset('vendor/bpmn-js/bpmn-navigated-viewer.production.min.js') }}"></script>
    <script src="{{ asset('assets/common.js') }}"></script>
    <script src="{{ asset('assets/compartilhamento-viewer.js') }}"></script>
</body>
</html>
