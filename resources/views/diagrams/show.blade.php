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

            {{-- Entry point for the Version history panel (see #10). --}}
            <button
                type="button"
                id="btn-version-history-entry"
                data-slot="version-history-entry"
                class="flex h-8 w-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                title="{{ __('messages.version_history') }}"
                aria-label="{{ __('messages.version_history') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" />
                </svg>
            </button>

            {{-- Entry point for the Collaborators / Shares panel (see #11). --}}
            <div data-slot="collaborators-entry">
                @can('manageCollaborators', $diagram)
                    <button
                        type="button"
                        id="btn-collaborators-entry"
                        class="flex h-8 w-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                        title="{{ __('messages.collaborators') }}"
                        aria-label="{{ __('messages.collaborators') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 0 0-3-3.87M9 20H4v-1a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v1Zm3-14a4 4 0 0 1 0 8m-6-4a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" />
                        </svg>
                    </button>
                @else
                    <div class="h-8 w-8" aria-hidden="true"></div>
                @endcan
            </div>
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
        data-lock-requested-by-notice="{{ __('messages.lock_requested_by_notice', ['name' => ':name']) }}"
        data-unlock-url="{{ route('diagrams.unlock', $diagram->slug) }}"
        data-versions-url="{{ route('diagrams.versions', $diagram->slug) }}"
        data-version-content-url-template="{{ route('diagrams.versions.content', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-restore-url-template="{{ route('diagrams.versions.restore', [$diagram->slug, 'VERSION_ID']) }}"
        data-restore-version-confirm="{{ __('messages.restore_version_confirm') }}"
        data-version-restored-notice="{{ __('messages.version_restored') }}"
        data-version-restore-failed-notice="{{ __('messages.version_restore_failed') }}"
        data-restore-label="{{ __('messages.restore') }}"
        data-select-to-compare-label="{{ __('messages.select_to_compare') }}"
        data-compare-selected-label="{{ __('messages.compare_selected') }}"
        data-compare-pair-confirm="{{ __('messages.compare_pair_confirm') }}"
        data-compare-pair-failed-notice="{{ __('messages.compare_pair_failed') }}"
        @can('manageCollaborators', $diagram)
            data-collaborators-url="{{ route('diagrams.collaborators.index', $diagram->slug) }}"
            data-collaborator-url-template="{{ route('diagrams.collaborators.update', [$diagram->slug, 'USER_ID']) }}"
            data-can-manage-shares="{{ auth()->user()->can('manageShares', $diagram) ? 'true' : 'false' }}"
            data-shares-url="{{ route('shares.index', $diagram->slug) }}"
            data-share-destroy-url-template="{{ route('shares.destroy', ['share' => 'SHARE_ID']) }}"
            data-invite-failed-notice="{{ __('messages.collaborator_invite_failed') }}"
            data-invited-notice="{{ __('messages.collaborator_invited') }}"
            data-approve-failed-notice="{{ __('messages.collaborator_approve_failed') }}"
            data-approved-notice="{{ __('messages.collaborator_approved') }}"
            data-remove-failed-notice="{{ __('messages.collaborator_remove_failed') }}"
            data-removed-notice="{{ __('messages.collaborator_removed') }}"
            data-remove-collaborator-confirm="{{ __('messages.remove_collaborator_confirm') }}"
            data-refuse-request-confirm="{{ __('messages.refuse_request_confirm') }}"
            data-no-collaborators-notice="{{ __('messages.no_collaborators_yet') }}"
            data-status-pending-label="{{ __('messages.status_pending') }}"
            data-status-approved-label="{{ __('messages.status_approved') }}"
            data-approve-label="{{ __('messages.approve') }}"
            data-refuse-label="{{ __('messages.refuse') }}"
            data-remove-label="{{ __('messages.remove') }}"
            data-revoke-label="{{ __('messages.revoke') }}"
            data-copy-link-label="{{ __('messages.copy_link') }}"
            data-share-create-failed-notice="{{ __('messages.share_create_failed') }}"
            data-share-created-notice="{{ __('messages.share_created') }}"
            data-share-revoke-failed-notice="{{ __('messages.share_revoke_failed') }}"
            data-share-revoked-notice="{{ __('messages.share_revoked') }}"
            data-revoke-share-confirm="{{ __('messages.revoke_share_confirm') }}"
            data-no-shares-notice="{{ __('messages.no_shares_yet') }}"
            data-link-copied-notice="{{ __('messages.link_copied') }}"
            data-link-copy-failed-notice="{{ __('messages.link_copy_failed') }}"
            data-expires-label-template="{{ __('messages.expires_label', ['time' => 'EXPIRES_AT']) }}"
            data-download-allowed-label="{{ __('messages.download_allowed') }}"
            data-download-not-allowed-label="{{ __('messages.download_not_allowed') }}"
        @endcan
        class="h-full"
    >
        <div class="app" id="app">
            <div
                id="lock-request-banner"
                class="lock-request-banner"
                role="status"
                hidden
            ></div>
            <header>
                <span class="brand">{{ __('messages.bpmn_editor') }}</span>
                <span class="credit">
                    {{ __('messages.author_label', ['name' => $diagram->author->name]) }}
                    {{ __('messages.diagram_owner_label', ['name' => $diagram->owner->name]) }}
                </span>
                <span class="spacer"></span>
                <input type="text" id="name" value="{{ $diagram->title }}" autocomplete="off" spellcheck="false">
                <span class="dirty" id="dirty" title="{{ __('messages.unsaved_changes') }}">●</span>
                <button type="button" class="primary" id="btn-save" title="Ctrl+S">{{ __('messages.save') }}</button>
                <button type="button" class="danger" id="btn-delete">{{ __('messages.delete') }}</button>
            </header>
            <aside id="version-panel" hidden>
                <div class="aside-head">
                    <span>{{ __('messages.versions') }}</span>
                    <button type="button" id="btn-history-close" aria-label="Close">✕</button>
                </div>
                <ul id="version-list"></ul>
                {{-- Compare any two Versions (ticket 36): shown once exactly two
                     checkboxes below are selected — see versions.js. --}}
                <div class="version-panel-footer" id="version-compare-footer" hidden>
                    <button type="button" id="btn-compare-selected">{{ __('messages.compare_selected') }}</button>
                </div>
            </aside>
            @can('manageCollaborators', $diagram)
                <aside id="collaborators-panel" hidden>
                    <div class="aside-head">
                        <span>{{ __('messages.collaborators_and_shares') }}</span>
                        <button type="button" id="btn-collaborators-close" aria-label="Close">✕</button>
                    </div>
                    <div class="collab-body">
                        <section class="collab-section">
                            <h3>{{ __('messages.collaborators') }}</h3>
                            <form id="collab-invite-form">
                                <input type="email" id="collab-invite-email" placeholder="{{ __('messages.invite_by_email') }}" autocomplete="off" required>
                                <button type="submit" class="primary">{{ __('messages.invite') }}</button>
                            </form>
                            <ul id="collab-list"></ul>
                        </section>
                        <section class="collab-section" id="shares-section">
                            <h3>{{ __('messages.shares') }}</h3>
                            <form id="share-create-form">
                                <label>{{ __('messages.expires_in_days') }}
                                    <input type="number" id="share-days" min="1" max="60" value="7">
                                </label>
                                <label class="checkbox">
                                    <input type="checkbox" id="share-allow-download">
                                    {{ __('messages.allow_download') }}
                                </label>
                                <button type="submit" class="primary">{{ __('messages.create_share') }}</button>
                            </form>
                            <ul id="share-list"></ul>
                        </section>
                    </div>
                </aside>
            @endcan
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
    @can('manageCollaborators', $diagram)
        <script type="module" src="{{ asset('assets/collaborators.js') }}"></script>
    @endcan
</x-layouts.app>
