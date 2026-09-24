<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagrama->title }} — Editor BPMN</title>
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body
    id="editor"
    data-slug="{{ $diagrama->slug }}"
    data-title="{{ $diagrama->title }}"
    data-content-url="{{ route('diagramas.content', $diagrama->slug) }}"
    data-save-url="{{ route('diagramas.save', $diagrama->slug) }}"
    data-rename-url="{{ route('diagramas.rename', $diagrama->slug) }}"
    data-delete-url="{{ route('diagramas.destroy', $diagrama->slug) }}"
    data-index-url="{{ route('diagramas.index') }}"
>
    <div class="app">
        <header>
            <span class="brand">Editor BPMN</span>
            <span class="spacer"></span>
            <input type="text" id="name" value="{{ $diagrama->title }}" autocomplete="off" spellcheck="false">
            <span class="dirty" id="dirty" title="Alterações não salvas">●</span>
            <button type="button" class="primary" id="btn-save" title="Ctrl+S">Salvar</button>
            <button type="button" class="danger" id="btn-delete">Excluir</button>
        </header>
        <main>
            <div id="canvas"></div>
            <div class="zoom">
                <button type="button" id="zoom-in" title="Aproximar">+</button>
                <button type="button" id="zoom-out" title="Afastar">−</button>
                <button type="button" id="zoom-fit" title="Ajustar à tela">⤢</button>
            </div>
        </main>
    </div>

    <script src="{{ asset('vendor/bpmn-js/bpmn-modeler.production.min.js') }}"></script>
    <script src="{{ asset('assets/common.js') }}"></script>
    <script src="{{ asset('assets/editor.js') }}"></script>
</body>
</html>
