<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $diagram->title }} — BPMN Studio</title>
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    id="viewer"
    data-title="{{ $diagram->title }}"
    data-allow-download="{{ $share->allow_download ? '1' : '0' }}"
    class="h-full font-sans text-gray-900"
>
    <div class="flex h-full flex-col">
        <header class="border-b border-gray-200 bg-white shadow-sm">
            <div class="flex items-center gap-4 px-4 py-3 sm:px-6">
                <h1 class="flex-1 truncate text-lg font-semibold text-gray-900">
                    {{ $diagram->title }}
                </h1>

                <div class="flex items-center gap-4">
                    @include('partials.language-switcher')

                    @if ($share->allow_download)
                        <div class="dropdown relative">
                            <button
                                type="button"
                                id="btn-download"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                {{ __('messages.download') }}
                            </button>
                            <div class="dropdown-menu absolute right-0 top-full z-20 hidden w-48 rounded-lg border border-gray-200 bg-white shadow-lg">
                                <button
                                    type="button"
                                    data-export="png"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg"
                                >
                                    {{ __('messages.png_image') }}
                                </button>
                                <button
                                    type="button"
                                    data-export="svg"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg"
                                >
                                    {{ __('messages.svg_image') }}
                                </button>
                                <button
                                    type="button"
                                    data-export="bpmn"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg"
                                >
                                    {{ __('messages.bpmn_file') }}
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </header>

        <main class="relative flex-1 overflow-hidden">
            <div id="canvas" class="h-full w-full"></div>
            <div id="message" class="absolute inset-0 flex items-center justify-center bg-gray-50 text-gray-600">
                {{ __('messages.loading') }}
            </div>

            <div id="zoom-controls" class="absolute bottom-20 right-4 flex flex-col gap-2">
                <button
                    type="button"
                    id="zoom-in"
                    title="{{ __('messages.zoom_in') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    +
                </button>
                <button
                    type="button"
                    id="zoom-out"
                    title="{{ __('messages.zoom_out') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    −
                </button>
                <button
                    type="button"
                    id="zoom-fit"
                    title="{{ __('messages.fit_to_screen') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    ⤢
                </button>
            </div>
        </main>
    </div>

    <script id="diagram-xml" type="application/xml">{!! $diagramXml !!}</script>
    <script src="{{ asset('vendor/bpmn-js/bpmn-navigated-viewer.production.min.js') }}"></script>
    <script src="{{ asset('assets/common.js') }}"></script>
    <script src="{{ asset('assets/share-viewer.js') }}"></script>
</body>
</html>
