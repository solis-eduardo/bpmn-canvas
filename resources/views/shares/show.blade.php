<x-layouts.app :title="$diagram->title" :fluid="true" :standard-header="true">
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

    {{-- Same arrangement as the Editor: the layout header holds only the global
         navigation; the page's own actions sit in a separate row below it. --}}
    <div class="flex h-full flex-col">
        <div id="view-actions" class="flex shrink-0 flex-wrap items-center gap-3 border-b border-gray-200 bg-white px-4 py-2 sm:px-6">
            @isset($backUrl)
                <x-button :href="$backUrl" variant="secondary" size="sm">
                    <x-icon name="arrow-left" />
                    {{ __('messages.back') }}
                </x-button>
            @endisset
            <span class="min-w-0 max-w-xs truncate text-sm font-medium text-gray-700" title="{{ $diagram->title }}">
                {{ $diagram->title }}
            </span>
            <span class="flex-1"></span>
            @if (isset($requestLockUrl))
                <div id="lock-request" class="flex items-center gap-2" data-request-url="{{ $requestLockUrl }}" data-show-url="{{ $showUrl }}">
                    <button type="button" id="btn-request-lock" class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 disabled:opacity-50">
                        <x-icon name="key" />
                        {{ __('messages.request_the_lock') }}
                    </button>
                    <span id="lock-request-waiting" class="max-w-xs text-xs text-gray-600" hidden>{{ __('messages.lock_request_waiting') }}</span>
                </div>
            @endif
            {{-- A Fork is an independent copy, available at any time; it is also
                 the way to keep working past someone else's Lock (CONTEXT.md, "Fork"). --}}
            @isset($forkUrl)
                <form method="POST" action="{{ $forkUrl }}">
                    @csrf
                    <x-button type="submit" variant="secondary" size="sm">
                        <x-icon name="copy" />
                        {{ __('messages.fork_diagram') }}
                    </x-button>
                    <label class="fork-copy-tags text-sm text-gray-700"><input type="checkbox" name="copy_tags" value="1"> {{ __('messages.fork_copy_tags') }}</label>
                </form>
            @endisset
            @if (isset($unlockUrl))
                <button
                    type="button"
                    id="btn-release-lock"
                    data-unlock-url="{{ $unlockUrl }}"
                    data-release-draft-message="{{ __('messages.release_lock_draft_admin') }}"
                    data-release-save-label="{{ __('messages.release_save_and_release') }}"
                    data-release-discard-label="{{ __('messages.release_discard_and_release') }}"
                    data-release-cancel-label="{{ __('messages.release_cancel') }}"
                    data-release-failed-notice="{{ __('messages.release_lock_failed') }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50"
                >
                    <x-icon name="key" />
                    {{ __('messages.force_release_lock') }}
                </button>
            @endif
            @if (! empty($downloadUrls))
                <div class="dropdown relative">
                    <button
                        type="button"
                        id="btn-download"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        <x-icon name="download" />
                        {{ __('messages.download') }}
                    </button>
                    <div class="dropdown-menu absolute right-0 top-full z-20 mt-1 min-w-[170px] rounded-md border border-gray-200 bg-white p-1 shadow-lg">
                        <a href="{{ $downloadUrls['png'] }}" download class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100">
                            <x-icon name="download" />
                            {{ __('messages.png_image') }}
                        </a>
                        <a href="{{ $downloadUrls['svg'] }}" download class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100">
                            <x-icon name="download" />
                            {{ __('messages.svg_image') }}
                        </a>
                        <a href="{{ $downloadUrls['pdf'] }}" download class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100">
                            <x-icon name="download" />
                            {{ __('messages.pdf_document') }}
                        </a>
                        <a href="{{ $downloadUrls['bpmn'] }}" download class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100">
                            <x-icon name="download" />
                            {{ __('messages.bpmn_file') }}
                        </a>
                    </div>
                </div>
            @endif
        </div>

        @if (isset($forks) && ($forkedFrom || $forks->isNotEmpty()))
            <dl id="fork-lineage" class="shrink-0 border-b border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 sm:px-6">
                @include('diagrams._fork-lineage')
            </dl>
        @endif

        <div id="viewer" class="relative min-h-0 flex-1" data-title="{{ $diagram->title }}" data-open-failed-notice="{{ __('messages.diagram_open_failed') }}">
            <div id="canvas" class="absolute inset-0 bg-white"></div>
            @isset($notice)
                <p
                    class="absolute left-4 right-4 top-4 z-10 rounded-md border px-3 py-2 text-sm font-semibold shadow-sm sm:right-auto sm:max-w-xl {{ ($noticeTone ?? 'blue') === 'red' ? 'border-red-300 bg-red-100 text-red-900' : 'border-blue-200 bg-blue-50 text-blue-900' }}"
                    role="{{ ($noticeTone ?? 'blue') === 'red' ? 'alert' : 'status' }}"
                    @if (! empty($lockedAt)) data-locked-at="{{ $lockedAt->toIso8601String() }}" @endif
                >{{ $notice }}</p>
            @endisset
            <div id="message" class="absolute inset-0 flex items-center justify-center px-6 text-center text-gray-500">
                {{ __('messages.loading') }}
            </div>
            <div class="absolute bottom-16 right-4 z-10 flex flex-col gap-1">
                <button type="button" id="zoom-in" title="{{ __('messages.zoom_in') }}" aria-label="{{ __('messages.zoom_in') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white shadow-sm hover:bg-gray-50"><x-icon name="plus" class="h-5 w-5" /></button>
                <button type="button" id="zoom-out" title="{{ __('messages.zoom_out') }}" aria-label="{{ __('messages.zoom_out') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white shadow-sm hover:bg-gray-50"><x-icon name="minus" class="h-5 w-5" /></button>
                <button type="button" id="zoom-fit" title="{{ __('messages.fit_to_screen') }}" aria-label="{{ __('messages.fit_to_screen') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white shadow-sm hover:bg-gray-50"><x-icon name="fit" class="h-5 w-5" /></button>
            </div>
        </div>
    </div>

    {{-- Non-executing JSON block: JSON_HEX_* escapes every `<`, `>`, `&` and quote, so the XML can't close the tag or start another one. --}}
    <script id="diagram-xml" type="application/json">@json($diagramXml, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
    <script src="{{ asset('vendor/bpmn-js/bpmn-navigated-viewer.production.min.js') }}"></script>
    <script type="module" src="{{ asset('assets/share-viewer.js') }}"></script>
    @isset($requestLockUrl)
        <script src="{{ asset('assets/locked.js') }}"></script>
    @endisset
</x-layouts.app>
