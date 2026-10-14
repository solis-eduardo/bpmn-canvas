{{-- The standalone build's page (ADR-0019), rendered once at build time by
     `php artisan standalone:render`, with the same layout and Editor components
     as the server build. Text comes from lang/ at runtime: App\Support\Ui turns
     every string into a data-i18n key that standalone/main.js fills in. --}}
<x-layouts.app :fluid="true" :standard-header="true">
    <x-slot:languageSwitcher>
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-600">
            {{ __('messages.language') }}
            <select id="language" class="rounded-md border border-gray-300 py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></select>
        </label>
    </x-slot:languageSwitcher>

    {{-- The list of Diagrams: the server's own listing, with no rows; each row is cloned from the template. --}}
    <div id="library" class="h-full overflow-y-auto" hidden>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 xl:max-w-[88rem]">
            @include('diagrams.partials.list', $list)
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <input type="file" id="import-file" accept=".bpmn,.xml,application/xml,text/xml" hidden>
                <x-button type="button" id="btn-import" variant="secondary">
                    <x-icon name="download" class="h-4 w-4 rotate-180" />
                    {{ __('standalone.import_bpmn') }}
                </x-button>
                <x-button type="button" id="btn-export-all" variant="secondary">
                    <x-icon name="download" />
                    {{ __('standalone.export_all') }}
                </x-button>
            </div>
            <p class="mt-4 text-sm text-gray-500">{{ __('standalone.storage_notice') }}</p>
            <template id="diagram-row">{!! $rowTemplate !!}</template>
        </div>
    </div>

    {{-- Screens of the server build the standalone page does not have yet (Home, Workspaces, Users, Profile) --}}
    <div id="placeholder" class="h-full overflow-y-auto" hidden>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <h1 id="placeholder-title" class="text-2xl font-semibold text-gray-900"></h1>
            <p class="mt-4 text-sm text-gray-500">{{ __('standalone.screen_unavailable') }}</p>
        </div>
    </div>

    {{-- The Editor: the server page's shell, minus what needs a server --}}
    <div id="editor-view" class="h-full" hidden>
        <div class="app" id="app">
            <x-editor.toolbar back-href="#/">
                <div class="dropdown">
                    <button type="button" id="btn-export"><x-icon name="download" class="inline h-4 w-4 align-text-bottom" /> {{ __('standalone.export') }}</button>
                    <div class="dropdown-menu">
                        <button type="button" data-export="png">{{ __('messages.png_image') }}</button>
                        <button type="button" data-export="svg">{{ __('messages.svg_image') }}</button>
                        <button type="button" data-export="bpmn">{{ __('messages.bpmn_file') }}</button>
                    </div>
                </div>
                <button type="button" id="btn-save-to-file">{{ __('standalone.save_to_file') }}</button>
            </x-editor.toolbar>
            <x-editor.version-panel />
            <nav class="panel-bar">
                <x-editor.version-toggle />
            </nav>
            <x-editor.canvas />
        </div>
    </div>

</x-layouts.app>
