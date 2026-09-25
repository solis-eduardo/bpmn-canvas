<x-layouts.app :title="$diagram->title" :fluid="true">
    <x-slot:head>
        <meta name="robots" content="noindex, nofollow">
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn.css') }}">
        <style>
            /* Toggled by assets/common.js (setupDropdowns), shared with the Editor. */
            .dropdown-menu { display: none; }
            .dropdown.open .dropdown-menu { display: block; }
        </style>
    </x-slot:head>

    <x-slot:headerActions>
        <div class="flex items-center gap-3">
            <span class="max-w-xs truncate text-sm font-medium text-gray-700" title="{{ $diagram->title }}">
                {{ $diagram->title }}
            </span>

            @if ($share->allow_download)
                <div class="dropdown relative">
                    <button
                        type="button"
                        id="btn-download"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('messages.download') }} ▾
                    </button>
                    <div class="dropdown-menu absolute right-0 top-full z-20 mt-1 min-w-[170px] rounded-md border border-gray-200 bg-white p-1 shadow-lg">
                        <button type="button" data-export="png" class="block w-full rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('messages.png_image') }}
                        </button>
                        <button type="button" data-export="svg" class="block w-full rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('messages.svg_image') }}
                        </button>
                        <button type="button" data-export="bpmn" class="block w-full rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('messages.bpmn_file') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </x-slot:headerActions>

    <div id="viewer" class="relative h-full" data-title="{{ $diagram->title }}">
        <div id="canvas" class="absolute inset-0 bg-white"></div>
        <div id="message" class="absolute inset-0 flex items-center justify-center px-6 text-center text-gray-500">
            {{ __('messages.loading') }}
        </div>
        <div class="absolute bottom-16 right-4 z-10 flex flex-col gap-1">
            <button type="button" id="zoom-in" title="{{ __('messages.zoom_in') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-lg shadow-sm hover:bg-gray-50">+</button>
            <button type="button" id="zoom-out" title="{{ __('messages.zoom_out') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-lg shadow-sm hover:bg-gray-50">−</button>
            <button type="button" id="zoom-fit" title="{{ __('messages.fit_to_screen') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-lg shadow-sm hover:bg-gray-50">⤢</button>
        </div>
    </div>

    <script id="diagram-xml" type="application/xml">{!! $diagramXml !!}</script>
    <script src="{{ asset('vendor/bpmn-js/bpmn-navigated-viewer.production.min.js') }}"></script>
    <script src="{{ asset('assets/common.js') }}"></script>
    <script src="{{ asset('assets/share-viewer.js') }}"></script>
</x-layouts.app>
