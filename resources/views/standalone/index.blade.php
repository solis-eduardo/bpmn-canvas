{{-- The standalone build's page (ADR-0019), rendered once at build time by
     `php artisan standalone:render`, with the same layout and Editor components
     as the server build. Text comes from lang/ at runtime: App\Support\Ui turns
     every string into a data-i18n key that standalone/main.js fills in. --}}
<x-layouts.app :fluid="true" :standard-header="true">
    <x-slot:languageSwitcher>
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-600">
            {{ \App\Support\Ui::text('messages.language') }}
            <select id="language" class="rounded-md border border-gray-300 py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></select>
        </label>
    </x-slot:languageSwitcher>

    {{-- The list of Diagrams --}}
    <div id="library" class="h-full overflow-y-auto" hidden>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold text-gray-900">{{ \App\Support\Ui::text('messages.diagrams') }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <x-button type="button" id="btn-import" variant="secondary">
                        <x-icon name="download" class="h-4 w-4 rotate-180" />
                        {{ \App\Support\Ui::text('standalone.import_bpmn') }}
                    </x-button>
                    <input type="file" id="import-file" accept=".bpmn,.xml,application/xml,text/xml" hidden>
                    <x-button type="button" id="btn-export-all" variant="secondary">
                        <x-icon name="download" />
                        {{ \App\Support\Ui::text('standalone.export_all') }}
                    </x-button>
                    <x-button type="button" id="btn-new">
                        <x-icon name="plus" />
                        {{ \App\Support\Ui::text('standalone.new_diagram') }}
                    </x-button>
                </div>
            </div>

            <p class="mt-4 text-sm text-gray-500">{{ \App\Support\Ui::text('standalone.storage_notice') }}</p>

            <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="w-full divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ \App\Support\Ui::text('messages.diagram_column_title') }}</th>
                            <th scope="col" class="px-4 py-3">{{ \App\Support\Ui::text('standalone.modified') }}</th>
                            <th scope="col" class="w-px whitespace-nowrap px-4 py-3">{{ \App\Support\Ui::text('messages.diagram_column_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="library-list" class="divide-y divide-gray-200 bg-white"></tbody>
                </table>
                <p id="library-empty" class="px-4 py-6 text-center text-sm text-gray-500" hidden>{{ \App\Support\Ui::text('messages.no_diagrams_yet') }}</p>
            </div>
        </div>
    </div>

    {{-- Screens of the server build the standalone page does not have yet (Home, Workspaces, Users, Profile) --}}
    <div id="placeholder" class="h-full overflow-y-auto" hidden>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <h1 id="placeholder-title" class="text-2xl font-semibold text-gray-900"></h1>
            <p class="mt-4 text-sm text-gray-500">{{ \App\Support\Ui::text('standalone.screen_unavailable') }}</p>
        </div>
    </div>

    {{-- The Editor: the server page's shell, minus what needs a server --}}
    <div id="editor-view" class="h-full" hidden>
        <div class="app" id="app">
            <x-editor.toolbar back-href="#/">
                <div class="dropdown">
                    <button type="button" id="btn-export"><x-icon name="download" class="inline h-4 w-4 align-text-bottom" /> {{ \App\Support\Ui::text('standalone.export') }}</button>
                    <div class="dropdown-menu">
                        <button type="button" data-export="png">{{ \App\Support\Ui::text('messages.png_image') }}</button>
                        <button type="button" data-export="svg">{{ \App\Support\Ui::text('messages.svg_image') }}</button>
                        <button type="button" data-export="bpmn">{{ \App\Support\Ui::text('messages.bpmn_file') }}</button>
                    </div>
                </div>
                <button type="button" id="btn-save-to-file">{{ \App\Support\Ui::text('standalone.save_to_file') }}</button>
            </x-editor.toolbar>
            <x-editor.version-panel />
            <nav class="panel-bar">
                <x-editor.version-toggle />
            </nav>
            <x-editor.canvas />
        </div>
    </div>

    <script type="module" src="./main.js"></script>
</x-layouts.app>
