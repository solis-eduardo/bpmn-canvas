<x-layouts.app :title="$diagram->title . ' | ' . __('messages.bpmn_editor')" :fluid="true">
    <x-slot:head>
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/diagram-js.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-js.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css') }}">
        @vite('resources/css/editor.css')
        <script type="importmap" nonce="{{ $cspNonce }}">
            {
                "imports": {
                    "bpmn-js-differ": "{{ asset('vendor/bpmn-js-differ/index.esm.js') }}",
                    "min-dash": "{{ asset('vendor/bpmn-js-differ/min-dash.esm.js') }}"
                }
            }
        </script>
    </x-slot:head>

    <x-slot:headerActions>
        <div class="flex flex-wrap items-center gap-2">
            <x-button :href="route('diagrams.index')" id="btn-back-to-list" variant="secondary">
                <x-icon name="arrow-left" />
                {{ __('messages.back') }}
            </x-button>
            @php($isHolder = (int) $diagram->locked_by === auth()->id())
            @if ($isHolder || (auth()->user()->is_admin && $diagram->locked_by !== null))
                <button
                    type="button"
                    id="btn-release-lock"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                >
                    <x-icon name="key" />
                    {{ __($isHolder ? 'messages.release_lock' : 'messages.force_release_lock') }}
                </button>
            @endif

            @can('manageTags', $diagram)
                <a
                    href="{{ route('diagrams.edit', $diagram->slug) }}"
                    id="btn-edit-tags"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                    title="{{ __('messages.edit_diagram_tags') }}"
                    aria-label="{{ __('messages.edit_diagram_tags') }}"
                >
                    <x-icon name="tag" class="h-5 w-5" />
                </a>
            @endcan

            {{-- Entry point for the Version history panel (see #10). --}}
            <button
                type="button"
                id="btn-version-history-entry"
                data-slot="version-history-entry"
                class="flex h-8 w-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                title="{{ __('messages.version_history') }}"
                aria-label="{{ __('messages.version_history') }}"
            >
                <x-icon name="clock" class="h-5 w-5" />
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
                        <x-icon name="users" class="h-5 w-5" />
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
        data-heartbeat-url="{{ route('diagrams.lock.update', $diagram->slug) }}"
        data-lock-requested-by-notice="{{ __('messages.lock_requested_by_notice', ['name' => ':name']) }}"
        data-unlock-url="{{ route('diagrams.lock.destroy', $diagram->slug) }}"
        data-release-unsaved-confirm="{{ __('messages.release_lock_unsaved_confirm') }}"
        data-versions-url="{{ route('diagrams.versions', $diagram->slug) }}"
        data-version-content-url-template="{{ route('diagrams.versions.content', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-compare-url="{{ route('diagrams.versions.compare', $diagram->slug) }}"
        data-version-restore-url-template="{{ route('diagrams.versions.restore', [$diagram->slug, 'VERSION_ID']) }}"
        data-restore-version-confirm="{{ __('messages.restore_version_confirm') }}"
        data-version-restored-notice="{{ __('messages.version_restored') }}"
        data-version-restore-failed-notice="{{ __('messages.version_restore_failed') }}"
        data-restore-label="{{ __('messages.restore') }}"
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
            data-follow-latest-version-label="{{ __('messages.follow_latest_version') }}"
            data-pinned-to-version-template="{{ __('messages.pinned_to_version', ['name' => 'VERSION_NAME']) }}"
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
                <button type="button" class="primary" id="btn-save" title="Ctrl+S"><x-icon name="save" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.save') }}</button>
                <button type="button" class="danger" id="btn-delete"><x-icon name="trash" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.delete') }}</button>
            </header>
            <aside id="version-panel" hidden>
                <div class="aside-head">
                    <span>{{ __('messages.versions') }}</span>
                    <button type="button" id="btn-history-close" aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                </div>
                <p class="version-hint">{{ __('messages.select_two_versions_hint') }}</p>
                <ul id="version-list"></ul>
                <div class="version-compare-bar">
                    <span id="version-compare-count">0/2</span>
                    <button type="button" id="btn-compare-selected" disabled><x-icon name="transfer" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.compare_selected') }}</button>
                </div>
            </aside>
            @can('manageCollaborators', $diagram)
                <aside id="collaborators-panel" hidden>
                    <div class="aside-head">
                        <span>{{ __('messages.collaborators_and_shares') }}</span>
                        <button type="button" id="btn-collaborators-close" aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                    </div>
                    <div class="collab-body">
                        <section class="collab-section">
                            <h3>{{ __('messages.collaborators') }}</h3>
                            <form id="collab-invite-form">
                                <input type="email" id="collab-invite-email" placeholder="{{ __('messages.invite_by_email') }}" autocomplete="off" required>
                                <button type="submit" class="primary"><x-icon name="user-plus" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.invite') }}</button>
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
                                <label>{{ __('messages.pin_to_version') }}
                                    <select id="share-pin-version">
                                        <option value="">{{ __('messages.follow_latest_version') }}</option>
                                    </select>
                                </label>
                                <button type="submit" class="primary"><x-icon name="plus" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.create_share') }}</button>
                            </form>
                            <ul id="share-list"></ul>
                        </section>
                    </div>
                </aside>
            @endcan
            <main>
                <div id="canvas"></div>
                <div class="zoom">
                    <button type="button" id="zoom-in" title="{{ __('messages.zoom_in') }}" aria-label="{{ __('messages.zoom_in') }}"><x-icon name="plus" /></button>
                    <button type="button" id="zoom-out" title="{{ __('messages.zoom_out') }}" aria-label="{{ __('messages.zoom_out') }}"><x-icon name="minus" /></button>
                    <button type="button" id="zoom-fit" title="{{ __('messages.fit_to_screen') }}" aria-label="{{ __('messages.fit_to_screen') }}"><x-icon name="fit" /></button>
                </div>
                <div class="diff-banner" id="diff-banner" hidden>
                    <strong id="diff-banner-label"></strong>
                    <span class="legend"><span class="swatch added"></span>{{ __('messages.diff_added') }}</span>
                    <span class="legend"><span class="swatch changed"></span>{{ __('messages.diff_changed') }}</span>
                    <span class="legend"><span class="swatch removed"></span>{{ __('messages.diff_removed') }}</span>
                    <button type="button" id="btn-diff-exit"><x-icon name="x-mark" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.exit_comparison') }}</button>
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
