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
        data-slug="{{ $diagram->slug }}"
        data-title="{{ $diagram->title }}"
        data-saved-notice="{{ __('messages.saved') }}"
        data-load-failed-notice="{{ __('messages.diagram_load_failed') }}"
        data-save-failed-notice="{{ __('messages.diagram_save_failed') }}"
        data-release-lock-failed-notice="{{ __('messages.release_lock_failed') }}"
        data-content-url="{{ route('diagrams.content', $diagram->slug) }}"
        data-save-url="{{ route('diagrams.save', $diagram->slug) }}"
        data-rename-url="{{ route('diagrams.rename', $diagram->slug) }}"
        data-delete-url="{{ route('diagrams.destroy', $diagram->slug) }}"
        data-index-url="{{ route('diagrams.index') }}"
        data-heartbeat-url="{{ route('diagrams.lock.update', $diagram->slug) }}"
        data-lock-requested-by-notice="{{ __('messages.lock_requested_by_notice', ['name' => ':name']) }}"
        data-unlock-url="{{ route('diagrams.lock.destroy', $diagram->slug) }}"
        data-is-lock-holder="{{ (int) $diagram->locked_by === auth()->id() ? '1' : '0' }}"
        data-release-draft-holder-message="{{ __('messages.release_lock_draft_holder') }}"
        data-release-draft-admin-message="{{ __('messages.release_lock_draft_admin') }}"
        data-release-save-label="{{ __('messages.release_save_and_release') }}"
        data-release-discard-label="{{ __('messages.release_discard_and_release') }}"
        data-release-cancel-label="{{ __('messages.release_cancel') }}"
        data-versions-url="{{ route('diagrams.versions', $diagram->slug) }}"
        data-version-content-url-template="{{ route('diagrams.versions.content', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-compare-url="{{ route('diagrams.versions.compare', $diagram->slug) }}"
        data-version-restore-url-template="{{ route('diagrams.versions.restore', [$diagram->slug, 'VERSION_ID']) }}"
        data-restore-version-confirm="{{ __('messages.restore_version_confirm') }}"
        data-version-restored-notice="{{ __('messages.version_restored') }}"
        data-version-restore-failed-notice="{{ __('messages.version_restore_failed') }}"
        data-restore-label="{{ __('messages.restore') }}"
        data-version-rename-url-template="{{ route('diagrams.versions.rename', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-promote-url-template="{{ route('diagrams.versions.promote', [$diagram->slug, 'VERSION_ID']) }}"
        data-version-delete-url-template="{{ route('diagrams.versions.delete', [$diagram->slug, 'VERSION_ID']) }}"
        data-can-edit-versions="{{ auth()->user()->can('update', $diagram) ? 'true' : 'false' }}"
        data-rename-version-label="{{ __('messages.rename_version') }}"
        data-rename-version-prompt="{{ __('messages.rename_version_prompt') }}"
        data-version-rename-failed-notice="{{ __('messages.version_rename_failed') }}"
        data-provisional-badge-label="{{ __('messages.provisional_badge') }}"
        data-current-badge-label="{{ __('messages.current_version_badge') }}"
        data-make-permanent-label="{{ __('messages.make_permanent') }}"
        data-version-made-permanent-notice="{{ __('messages.version_made_permanent') }}"
        data-version-promote-failed-notice="{{ __('messages.version_promote_failed') }}"
        data-delete-version-label="{{ __('messages.delete') }}"
        data-delete-version-confirm="{{ __('messages.delete_version_confirm') }}"
        data-version-deleted-notice="{{ __('messages.version_deleted') }}"
        data-version-delete-failed-notice="{{ __('messages.version_delete_failed') }}"
        data-version-by-template="{{ __('messages.version_by_author', ['name' => ':name']) }}"
        data-compare-with-current-label="{{ __('messages.compare_with_current') }}"
        data-no-versions-notice="{{ __('messages.no_versions_yet') }}"
        data-element-types="{{ json_encode(__('messages.bpmn_element_types'), JSON_UNESCAPED_UNICODE) }}"
        data-versions-load-failed-notice="{{ __('messages.versions_load_failed') }}"
        data-version-compare-failed-notice="{{ __('messages.version_compare_failed') }}"
        data-versions-compare-failed-notice="{{ __('messages.versions_compare_failed') }}"
        data-comparing-with-template="{{ __('messages.comparing_with', ['version' => ':version']) }}"
        data-comparing-versions-template="{{ __('messages.comparing_versions', ['older' => ':older', 'newer' => ':newer']) }}"
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
                <x-button :href="route('diagrams.index')" id="btn-back-to-list" variant="secondary" size="sm">
                    <x-icon name="arrow-left" />
                    {{ __('messages.back') }}
                </x-button>
                <span class="brand">{{ $diagram->title }}</span>
                <span class="spacer"></span>
                <form method="POST" action="{{ route('diagrams.fork', $diagram->slug) }}">
                    @csrf
                    <button type="submit" id="btn-fork">
                        <x-icon name="copy" class="inline h-4 w-4 align-text-bottom" />
                        {{ __('messages.fork_diagram') }}
                    </button>
                </form>
                @php($isHolder = (int) $diagram->locked_by === auth()->id())
                @if ($isHolder || (auth()->user()->is_admin && $diagram->locked_by !== null))
                    <button type="button" id="btn-release-lock">
                        <x-icon name="key" class="inline h-4 w-4 align-text-bottom" />
                        {{ __($isHolder ? 'messages.release_lock' : 'messages.force_release_lock') }}
                    </button>
                @endif
                <span class="dirty" id="dirty" title="{{ __('messages.unsaved_changes') }}">●</span>
                <button type="button" class="primary" id="btn-save" title="Ctrl+S"><x-icon name="save" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.save') }}</button>            </header>
            <aside id="info-panel" hidden>
                <div class="aside-head">
                    <span>{{ __('messages.diagram_info') }}</span>
                    <button type="button" id="btn-info-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                </div>
                <dl class="collab-body info-list">
                    <div><dt>{{ __('messages.diagram_column_title') }}</dt><dd>{{ $diagram->title }}</dd></div>
                    <div><dt>{{ __('messages.diagram_column_author') }}</dt><dd>{{ $diagram->author->name }}</dd></div>
                    <div><dt>{{ __('messages.diagram_column_owner') }}</dt><dd>{{ $diagram->owner->name }}</dd></div>
                    <div><dt>{{ __('messages.workspace_filter_label') }}</dt><dd>{{ $diagram->workspace?->name ?? __('messages.no_workspace') }}</dd></div>
                    <div>
                        <dt>{{ __('messages.diagram_column_tags') }}</dt>
                        <dd>{{ $diagram->tags->isEmpty() ? '—' : $diagram->tags->pluck('name')->implode(', ') }}</dd>
                    </div>
                    <div><dt>{{ __('messages.collaborators') }}</dt><dd>{{ $diagram->approved_collaborators_count }}</dd></div>
                    <div>
                        <dt>{{ __('messages.diagram_column_lock') }}</dt>
                        <dd>{{ $diagram->hasActiveLock() ? $diagram->lockedBy->name : __('messages.not_locked') }}</dd>
                    </div>
                    <div><dt>{{ __('messages.created_at_label') }}</dt><dd><x-timestamp :at="$diagram->created_at" /></dd></div>
                    <div><dt>{{ __('messages.diagram_column_modified') }}</dt><dd><x-timestamp :at="$diagram->updated_at" /></dd></div>
                    <div><dt>{{ __('messages.slug_label') }}</dt><dd class="break-all">{{ $diagram->slug }}</dd></div>
                </dl>
            </aside>
            @can('manageTags', $diagram)
                <aside id="data-panel" hidden>
                    <div class="aside-head">
                        <span>{{ __('messages.diagram_tags_heading') }}</span>
                        <button type="button" id="btn-data-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
                    </div>
                    <form method="POST" action="{{ route('diagrams.tags.sync', $diagram->slug) }}" class="collab-body">
                        @csrf
                        @method('PUT')
                        <x-tag-select
                            name="tags[]"
                            :options="$tagNames"
                            :selected="$diagram->tags->pluck('name')->all()"
                            multiple
                            scope="diagram-tags-{{ $diagram->id }}"
                            placeholder="{{ __('messages.add_tag_placeholder') }}"
                        />
                        <button type="submit" class="primary"><x-icon name="tag" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.save_tags') }}</button>
                    </form>
                </aside>
            @endcan
            <aside id="version-panel" hidden>
                <div class="aside-head">
                    <span>{{ __('messages.versions') }}</span>
                    <button type="button" id="btn-history-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
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
                @can('manageTags', $diagram)
                    <button
                        type="button"
                        id="btn-data-entry"
                        class="panel-toggle"
                        data-panel-toggle="data-panel"
                        title="{{ __('messages.edit_diagram_tags') }}"
                        aria-label="{{ __('messages.edit_diagram_tags') }}"
                    ><x-icon name="tag" /></button>
                @endcan
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
    @if (auth()->user()->can('manageCollaborators', $diagram) || auth()->user()->can('manageShares', $diagram))
        <script type="module" src="{{ asset('assets/collaborators.js') }}"></script>
    @endif
</x-layouts.app>
