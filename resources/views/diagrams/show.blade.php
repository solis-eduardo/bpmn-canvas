<x-layouts.app :title="$diagram->title . ' — ' . __('messages.bpmn_editor')" :fluid="true">
    <x-slot:head>
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
        <script type="importmap">
            {
                "imports": {
                    "bpmn-js-differ": "{{ asset('vendor/bpmn-js-differ/index.esm.js') }}",
                    "min-dash": "{{ asset('vendor/bpmn-js-differ/min-dash.esm.js') }}"
                }
            }
        </script>
    </x-slot:head>

    <x-slot:headerActions>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('diagrams.index') }}"
                id="btn-back-to-list"
                class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >
                &larr; {{ __('messages.back_to_diagrams') }}
            </a>

            {{-- Reserved entry point for the Version history panel (see #10). --}}
            <div
                id="btn-version-history-entry"
                data-slot="version-history-entry"
                class="h-8 w-8"
                aria-hidden="true"
                title="{{ __('messages.version_history') }}"
            ></div>

            {{-- Reserved entry point for Collaborators / Shares (see #11). --}}
            <div
                id="btn-collaborators-entry"
                data-slot="collaborators-entry"
                class="h-8 w-8"
                aria-hidden="true"
                title="{{ __('messages.collaborators') }}"
            ></div>
        </div>
    </x-slot:headerActions>

    <div
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
        data-versions-url="{{ route('diagrams.versions', $diagram->slug) }}"
        data-version-content-url-template="{{ route('diagrams.versions.content', [$diagram->slug, 'VERSION_ID']) }}"
        class="h-full"
    >
        <div class="app" id="app">
            <header>
                <span class="brand">{{ __('messages.bpmn_editor') }}</span>
                <span class="credit">
                    {{ __('messages.author_label', ['name' => $diagram->author->name]) }}
                    {{ __('messages.diagram_owner_label', ['name' => $diagram->owner->name]) }}
                </span>
                <span class="spacer"></span>
                <input type="text" id="name" value="{{ $diagram->title }}" autocomplete="off" spellcheck="false">
                <span class="dirty" id="dirty" title="{{ __('messages.unsaved_changes') }}">●</span>
                <button type="button" id="btn-history">{{ __('messages.history') }}</button>
                <button type="button" class="primary" id="btn-save" title="Ctrl+S">{{ __('messages.save') }}</button>
                <button type="button" class="danger" id="btn-delete">{{ __('messages.delete') }}</button>
            </header>
            <aside id="version-panel" hidden>
                <div class="aside-head">
                    <span>{{ __('messages.versions') }}</span>
                    <button type="button" id="btn-history-close" aria-label="Close">✕</button>
                </div>
                <ul id="version-list"></ul>
            </aside>
            <main>
                <div id="canvas"></div>
                <div class="zoom">
                    <button type="button" id="zoom-in" title="{{ __('messages.zoom_in') }}">+</button>
                    <button type="button" id="zoom-out" title="{{ __('messages.zoom_out') }}">−</button>
                    <button type="button" id="zoom-fit" title="{{ __('messages.fit_to_screen') }}">⤢</button>
                </div>
                <div class="diff-banner" id="diff-banner" hidden>
                    <strong id="diff-banner-label"></strong>
                    <span class="legend"><span class="swatch added"></span>{{ __('messages.diff_added') }}</span>
                    <span class="legend"><span class="swatch changed"></span>{{ __('messages.diff_changed') }}</span>
                    <span class="legend"><span class="swatch removed"></span>{{ __('messages.diff_removed') }}</span>
                    <button type="button" id="btn-diff-exit">{{ __('messages.exit_comparison') }}</button>
                </div>
                <div class="diff-removed-panel" id="diff-removed-panel" hidden>
                    <div class="head">{{ __('messages.diff_removed_elements') }}</div>
                    <ul id="diff-removed-list"></ul>
                </div>
            </main>
        </div>
    </div>

    <script src="{{ asset('vendor/bpmn-js/bpmn-modeler.production.min.js') }}"></script>
    <script src="{{ asset('assets/common.js') }}"></script>
    <script src="{{ asset('assets/editor.js') }}"></script>
    <script type="module" src="{{ asset('assets/diff.js') }}"></script>
    <script type="module" src="{{ asset('assets/versions.js') }}"></script>
</x-layouts.app>
