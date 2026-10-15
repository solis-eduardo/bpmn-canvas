{{-- The Diagram's Activity panel (CONTEXT.md, "Activity Log"; ADR-0025), shared by the Editor and the
     read-only Viewer (where Administrators land): public/assets/activity.js fills it. --}}
@props(['diagram', 'class' => ''])

<aside
    id="activity-panel"
    class="{{ $class }}"
    hidden
    data-activity-url="{{ route('diagrams.activity', $diagram->slug) }}"
    data-time-zone="{{ auth()->user()->displayTimezone() }}"
    data-labels="{{ json_encode(['empty' => __('messages.activity_empty'), 'noMatch' => __('messages.activity_no_match'), 'loadFailed' => __('messages.activity_load_failed'), 'loadMore' => __('messages.activity_load_more')], JSON_UNESCAPED_UNICODE) }}"
>
    <div class="aside-head">
        <span>{{ __('messages.activity') }}</span>
        <button type="button" id="btn-activity-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
    </div>
    <div class="collab-body">
        <form id="activity-filters" class="act-filters">
            <select name="action" aria-label="{{ __('messages.activity_column_action') }}">
                <option value="">{{ __('messages.activity_all_actions') }}</option>
                @foreach (\App\Models\ActivityLog::ACTIONS as $name)
                    <option value="{{ $name }}">{{ __('messages.activity_action_'.str_replace('.', '_', $name)) }}</option>
                @endforeach
            </select>
            <input type="search" name="actor" placeholder="{{ __('messages.activity_column_actor') }}" aria-label="{{ __('messages.activity_column_actor') }}">
            <input type="date" name="from" title="{{ __('messages.activity_filter_from') }}" aria-label="{{ __('messages.activity_filter_from') }}">
            <input type="date" name="to" title="{{ __('messages.activity_filter_to') }}" aria-label="{{ __('messages.activity_filter_to') }}">
            <button type="submit"><x-icon name="funnel" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.apply_filters') }}</button>
        </form>
        <p id="activity-error" class="info-error" role="alert" hidden></p>
        <ul id="activity-list" class="act-list"></ul>
        <button type="button" id="activity-more" hidden></button>
    </div>
</aside>
