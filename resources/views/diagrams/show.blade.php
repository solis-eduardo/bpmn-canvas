<x-layouts.app :title="$diagram->title . ' | ' . __('messages.bpmn_editor')" :fluid="true" :standard-header="true">
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

    <div
        id="editor"
        data-lang-url="{{ asset('assets/lang/'.app()->getLocale().'.json') }}"
        data-slug="{{ $diagram->slug }}"
        data-title="{{ $diagram->title }}"
        data-save-failed-notice="{{ __('messages.diagram_save_failed') }}"
        data-release-lock-failed-notice="{{ __('messages.release_lock_failed') }}"
        data-content-url="{{ route('diagrams.content', $diagram->slug) }}"
        data-save-url="{{ route('diagrams.save', $diagram->slug) }}"
        data-fork-url="{{ route('diagrams.fork', $diagram->slug) }}"
        data-rename-url="{{ route('diagrams.rename', $diagram->slug) }}"
        data-tags-url="{{ route('diagrams.tags.sync', $diagram->slug) }}"
        data-document-title-suffix=" | {{ __('messages.bpmn_editor') }}"
        data-renamed-notice="{{ __('messages.diagram_renamed') }}"
        data-rename-failed-notice="{{ __('messages.diagram_rename_failed') }}"
        data-tags-saved-notice="{{ __('messages.tags_saved') }}"
        data-tags-save-failed-notice="{{ __('messages.tags_save_failed') }}"
        @can('diagram-owner.transfer', $diagram)
            data-owner-url="{{ route('diagrams.owner.transfer', $diagram->slug) }}"
            data-owner-transferred-notice="{{ __('messages.owner_transferred') }}"
            data-owner-transfer-failed-notice="{{ __('messages.owner_transfer_failed') }}"
        @endcan
        data-delete-url="{{ route('diagrams.destroy', $diagram->slug) }}"
        data-index-url="{{ route('diagrams.index') }}"
        data-lock-acquire-url="{{ route('diagrams.lock.store', $diagram->slug) }}"
        data-open-failed-notice="{{ __('messages.diagram_open_failed') }}"
        data-show-url="{{ route('diagrams.show', $diagram->slug) }}"
        data-heartbeat-url="{{ route('diagrams.lock.update', $diagram->slug) }}"
        data-lock-url="{{ route('diagrams.lock.request', $diagram->slug) }}"
        data-lock-lost-notice="{{ __('messages.lock_lost_notice') }}"
        data-reacquire-lock-failed-notice="{{ __('messages.reacquire_lock_failed') }}"
        data-lock-requested-by-notice="{{ __('messages.lock_requested_by_notice', ['name' => ':name']) }}"
        data-unlock-url="{{ route('diagrams.lock.destroy', $diagram->slug) }}"
        data-is-lock-holder="1"
        data-release-draft-holder-message="{{ __('messages.release_lock_draft_holder') }}"
        data-release-draft-admin-message="{{ __('messages.release_lock_draft_admin') }}"
        data-release-save-label="{{ __('messages.release_save_and_release') }}"
        data-release-discard-label="{{ __('messages.release_discard_and_release') }}"
        data-release-cancel-label="{{ __('messages.release_cancel') }}"
        data-versions-url="{{ route('diagrams.versions', $diagram->slug) }}"
        data-time-zone="{{ auth()->user()->displayTimezone() }}"
        data-version-content-url-template="{{ route('diagrams.versions.content', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-restore-url-template="{{ route('diagrams.versions.restore', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-rename-url-template="{{ route('diagrams.versions.rename', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-promote-url-template="{{ route('diagrams.versions.promote', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-delete-url-template="{{ route('diagrams.versions.delete', [$diagram->slug, 'VERSION_ID']) }}"
        data-can-edit-versions="{{ auth()->user()->can('update', $diagram) ? 'true' : 'false' }}"
        data-collaborators-load-failed-notice="{{ __('messages.collaborators_load_failed') }}"
        data-shares-load-failed-notice="{{ __('messages.shares_load_failed') }}"
        @can('manageCollaborators', $diagram)
            data-collaborators-url="{{ route('diagrams.collaborators.index', $diagram->slug) }}"
            data-collaborator-url-template="{{ route('diagrams.collaborators.update', [$diagram->slug, 'USER_ID']) }}"
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
            data-can-assign-roles="{{ auth()->user()->can('collaborator.assignRole', $diagram) ? 'true' : 'false' }}"
            data-current-user-id="{{ auth()->id() }}"
            data-role-label="{{ __('messages.collaborator_role') }}"
            data-role-collaborator-label="{{ __('messages.role_collaborator') }}"
            data-role-manager-label="{{ __('messages.role_manager') }}"
            data-role-changed-notice="{{ __('messages.collaborator_role_changed') }}"
            data-role-change-failed-notice="{{ __('messages.collaborator_role_change_failed') }}"
        @endcan
        @can('manageShares', $diagram)
            data-shares-url="{{ route('shares.index', $diagram->slug) }}"
            data-share-destroy-url-template="{{ route('shares.destroy', ['share' => 'SHARE_ID']) }}"
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
            data-share-created-by-template="{{ __('messages.share_created_by', ['name' => 'CREATOR_NAME', 'date' => 'CREATED_AT']) }}"
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
            <div id="lock-lost-banner" class="lock-request-banner lock-lost-banner" role="alert" hidden>
                <span class="lock-lost-text"></span>
                <button type="button" class="lock-lost-reacquire">{{ __('messages.reacquire_lock') }}</button>
                <button type="button" class="lock-lost-dismiss">{{ __('messages.lock_lost_dismiss') }}</button>
            </div>
            <header>
                <x-button :href="route('diagrams.index')" id="btn-back-to-list" variant="secondary" size="sm">
                    <x-icon name="arrow-left" />
                    {{ __('messages.back') }}
                </x-button>
                <span class="brand" id="editor-brand">{{ $diagram->title }}</span>
                <span class="spacer"></span>
                <form method="POST" action="{{ route('diagrams.fork', $diagram->slug) }}">
                    @csrf
                    <button type="submit" id="btn-fork">
                        <x-icon name="copy" class="inline h-4 w-4 align-text-bottom" />
                        {{ __('messages.fork_diagram') }}
                    </button>
                    <label class="fork-copy-tags"><input type="checkbox" name="copy_tags" value="1"> {{ __('messages.fork_copy_tags') }}</label>
                </form>
                {{-- The Editor is only ever open to its Lock's holder: the Editor acquires the Lock
                     on load (ADR-0003), and a refusal sends the page to the Viewer instead. --}}
                <button type="button" id="btn-release-lock">
                    <x-icon name="key" class="inline h-4 w-4 align-text-bottom" />
                    {{ __('messages.release_lock') }}
                </button>
                <span class="dirty" id="dirty" title="{{ __('messages.unsaved_changes') }}">●</span>
                <button type="button" class="primary" id="btn-save" title="Ctrl+S"><x-icon name="save" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.save') }}</button>            </header>
            <aside id="info-panel" hidden>
                <div class="aside-head">
                    <span>{{ __('messages.diagram_info') }}</span>
                    <button type="button" id="btn-info-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                </div>
                <dl class="collab-body info-list">
                    {{-- Whoever reaches the Editor can edit the Diagram, so Title and Tags are always editable here;
                         the changes go through the JSON endpoints (issue #195). --}}
                    <div>
                        <dt>{{ __('messages.diagram_column_title') }}</dt>
                        <dd>
                            <div id="info-title-view" class="info-inline">
                                <span id="info-title">{{ $diagram->title }}</span>
                                <button type="button" id="info-title-edit" title="{{ __('messages.edit_title') }}" aria-label="{{ __('messages.edit_title') }}"><x-icon name="pencil" /></button>
                            </div>
                            <form id="info-title-form" class="info-inline" hidden>
                                <input type="text" id="info-title-input" name="title" maxlength="255" required value="{{ $diagram->title }}" aria-label="{{ __('messages.diagram_column_title') }}">
                                <button type="submit" class="primary">{{ __('messages.save') }}</button>
                                <button type="button" id="info-title-cancel">{{ __('messages.cancel') }}</button>
                            </form>
                            <p id="info-title-error" class="info-error" role="alert" hidden></p>
                        </dd>
                    </div>
                    <div><dt>{{ __('messages.diagram_column_author') }}</dt><dd>{{ $diagram->author->name }}</dd></div>
                    <div>
                        <dt>{{ __('messages.diagram_column_owner') }}</dt>
                        <dd class="info-inline">
                            <span id="info-owner">{{ $diagram->owner->name }}</span>
                            @can('diagram-owner.transfer', $diagram)
                                <button type="button" id="info-owner-transfer">{{ __('messages.transfer') }}</button>
                            @endcan
                        </dd>
                    </div>
                    <div><dt>{{ __('messages.workspace_filter_label') }}</dt><dd>{{ $diagram->workspace?->name ?? __('messages.no_workspace') }}</dd></div>
                    <div>
                        <dt>{{ __('messages.diagram_column_tags') }}</dt>
                        <dd>
                            <form id="info-tags-form">
                                <x-tag-select
                                    name="tags[]"
                                    :options="$tagNames"
                                    :selected="$diagram->tags->pluck('name')->all()"
                                    multiple
                                    wrapper-class="w-full"
                                    placeholder="{{ __('messages.add_tag_placeholder') }}"
                                />
                            </form>
                        </dd>
                    </div>
                    <div><dt>{{ __('messages.collaborators') }}</dt><dd>{{ $diagram->approved_collaborators_count }}</dd></div>
                    <div>
                        <dt>{{ __('messages.diagram_column_lock') }}</dt>
                        <dd>{{ $diagram->hasActiveLock() ? $diagram->lockedBy->name : __('messages.not_locked') }}</dd>
                    </div>
                    <div><dt>{{ __('messages.created_at_label') }}</dt><dd><x-timestamp :at="$diagram->created_at" /></dd></div>
                    <div><dt>{{ __('messages.diagram_column_modified') }}</dt><dd><x-timestamp :at="$diagram->updated_at" /></dd></div>
                    <div><dt>{{ __('messages.slug_label') }}</dt><dd class="break-all" id="info-slug">{{ $diagram->slug }}</dd></div>
                    @include('diagrams._fork-lineage')
                </dl>
                <p class="info-footer">
                    <a id="info-edit-link" href="{{ route('diagrams.edit', $diagram->slug) }}">{{ __('messages.edit_diagram') }}</a>
                </p>
            </aside>
            <aside id="version-panel" hidden>
                <div class="aside-head">
                    <span>{{ __('messages.versions') }}</span>
                    <button type="button" id="btn-history-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                </div>
                <p class="version-hint">{{ __('messages.select_two_versions_hint') }}</p>
                <label class="fork-copy-tags version-hint"><input type="checkbox" id="version-fork-copy-tags"> {{ __('messages.fork_copy_tags') }}</label>
                <ul id="version-list"></ul>
                <div class="version-compare-bar">
                    <span id="version-compare-count">0/2</span>
                    <button type="button" id="btn-compare-selected" disabled><x-icon name="transfer" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.compare_selected') }}</button>
                </div>
            </aside>
            @can('manageCollaborators', $diagram)
                <aside id="collaborators-panel" hidden>
                    <div class="aside-head">
                        <span>{{ __('messages.collaborators') }}</span>
                        <button type="button" id="btn-collaborators-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                    </div>
                    <div class="collab-body">
                        <section class="collab-section">
                            <form id="collab-invite-form">
                                <x-user-select
                                    :options="$collaboratorOptions"
                                    scope="collab-invite"
                                    :required="false"
                                    :label="__('messages.invite_collaborator')"
                                    :placeholder="__('messages.select_user_placeholder')"
                                />
                                @can('collaborator.assignRole', $diagram)
                                    <select id="collab-invite-role" aria-label="{{ __('messages.collaborator_role') }}">
                                        <option value="collaborator">{{ __('messages.role_collaborator') }}</option>
                                        <option value="manager">{{ __('messages.role_manager') }}</option>
                                    </select>
                                @endcan
                                <button type="submit" class="primary"><x-icon name="user-plus" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.invite') }}</button>
                            </form>
                            <ul id="collab-list"></ul>
                        </section>
                    </div>
                </aside>
            @endcan
            @can('manageShares', $diagram)
                <aside id="shares-panel" hidden>
                    <div class="aside-head">
                        <span>{{ __('messages.shares') }}</span>
                        <button type="button" id="btn-shares-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                    </div>
                    <div class="collab-body">
                        <section class="collab-section" id="shares-section">
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
            {{-- Always visible, with the side panel open or closed; the open panel's button is highlighted. --}}
            <nav class="panel-bar" aria-label="{{ __('messages.bpmn_editor') }}">
                <button
                    type="button"
                    id="btn-info-entry"
                    class="panel-toggle"
                    data-panel-toggle="info-panel"
                    title="{{ __('messages.diagram_info') }}"
                    aria-label="{{ __('messages.diagram_info') }}"
                ><x-icon name="information-circle" /></button>
                <button
                    type="button"
                    id="btn-version-history-entry"
                    class="panel-toggle"
                    data-panel-toggle="version-panel"
                    title="{{ __('messages.version_history') }}"
                    aria-label="{{ __('messages.version_history') }}"
                ><x-icon name="clock" /></button>
                @can('manageCollaborators', $diagram)
                    <button
                        type="button"
                        id="btn-collaborators-entry"
                        class="panel-toggle"
                        data-panel-toggle="collaborators-panel"
                        title="{{ __('messages.collaborators') }}"
                        aria-label="{{ __('messages.collaborators') }}"
                    ><x-icon name="users" /></button>
                @endcan
                @can('manageShares', $diagram)
                    <button
                        type="button"
                        id="btn-shares-entry"
                        class="panel-toggle"
                        data-panel-toggle="shares-panel"
                        title="{{ __('messages.shares') }}"
                        aria-label="{{ __('messages.shares') }}"
                    ><x-icon name="link" /></button>
                @endcan
            </nav>
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
                <div class="diff-summary-panel" id="diff-summary-panel" hidden>
                    <div class="head">{{ __('messages.diff_summary') }}</div>
                    <div id="diff-summary-body"></div>
                </div>
            </main>
            @can('diagram-owner.transfer', $diagram)
                <dialog id="owner-transfer-dialog" class="owner-transfer-dialog" aria-labelledby="owner-transfer-title">
                    <form id="owner-transfer-form">
                        <h2 id="owner-transfer-title">{{ __('messages.transfer_ownership') }}</h2>
                        <x-user-select
                            :options="$ownerCandidates"
                            scope="owner-transfer"
                            :required="false"
                            :label="__('messages.diagram_column_owner')"
                            :placeholder="__('messages.select_new_owner')"
                        />
                        <label class="checkbox">
                            <input type="checkbox" id="owner-transfer-give-up" name="give_up_access" value="1">
                            {{ __('messages.give_up_access') }}
                        </label>
                        <p id="owner-transfer-error" class="info-error" role="alert" hidden></p>
                        <div class="dialog-actions">
                            <button type="button" id="owner-transfer-cancel">{{ __('messages.cancel') }}</button>
                            <button type="submit" class="primary">{{ __('messages.transfer') }}</button>
                        </div>
                    </form>
                </dialog>
            @endcan
        </div>
    </div>

    <script src="{{ asset('vendor/bpmn-js/bpmn-modeler.production.min.js') }}"></script>
    <script type="module" src="{{ asset('assets/editor.js') }}"></script>
    <script type="module" src="{{ asset('assets/diagram-settings.js') }}"></script>
    @if (auth()->user()->can('manageCollaborators', $diagram) || auth()->user()->can('manageShares', $diagram))
        <script type="module" src="{{ asset('assets/collaborators.js') }}"></script>
    @endif
</x-layouts.app>
