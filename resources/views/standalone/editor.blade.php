{{-- The standalone build's Editor page (ADR-0019): the layout and Editor components
     of the server build, minus what needs a server. Rendered at build time by
     `php artisan standalone:render`. --}}
<x-layouts.app :fluid="true" :standard-header="true">
    {{-- The Editor: the server page's shell, minus what needs a server --}}
    <div id="editor-view" class="h-full">
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
