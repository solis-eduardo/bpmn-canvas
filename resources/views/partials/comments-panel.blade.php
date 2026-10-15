{{-- The Comments panel (CONTEXT.md, "Comment Thread"; ADR-0024), shared by the Editor and the
     read-only Viewer: public/assets/comments.js fills it. Its strings stay on data-* (server-only script). --}}
@props(['diagram', 'class' => ''])

@php
    $labels = [
        'placeholder' => __('messages.comment_placeholder'),
        'post' => __('messages.comment_post'),
        'reply' => __('messages.comment_reply'),
        'resolve' => __('messages.comment_resolve'),
        'reopen' => __('messages.comment_reopen'),
        'resolved' => __('messages.comment_resolved'),
        'resolvedBy' => __('messages.comment_resolved_by', ['name' => ':name']),
        'orphaned' => __('messages.comment_orphaned'),
        'onDiagram' => __('messages.comment_on_diagram'),
        'onElement' => __('messages.comment_on_element', ['element' => ':element']),
        'hint' => __('messages.comment_select_hint'),
        'empty' => __('messages.comment_no_threads'),
        'loadFailed' => __('messages.comments_load_failed'),
        'postFailed' => __('messages.comment_post_failed'),
        'resolveFailed' => __('messages.comment_resolve_failed'),
        'showOnCanvas' => __('messages.comment_show_on_canvas'),
    ];
@endphp

<aside
    id="comments-panel"
    class="{{ $class }}"
    hidden
    data-max-length="{{ \App\Models\Comment::MAX_BODY_LENGTH }}"
    data-time-zone="{{ auth()->user()->displayTimezone() }}"
    data-comments-url="{{ route('diagrams.comments.index', $diagram->slug) }}"
    data-labels="{{ json_encode($labels, JSON_UNESCAPED_UNICODE) }}"
>
    <div class="aside-head">
        <span>{{ __('messages.comments') }}</span>
        <button type="button" id="btn-comments-close" data-panel-close aria-label="{{ __('messages.close') }}"><x-icon name="x-mark" /></button>
    </div>
    <div class="collab-body">
        <form id="comment-form" class="cmt-form">
            <p id="comment-target" class="cmt-target"></p>
            <textarea id="comment-body" rows="3" maxlength="{{ \App\Models\Comment::MAX_BODY_LENGTH }}" required></textarea>
            <button type="submit" class="primary" id="comment-submit"></button>
        </form>
        <p id="comment-error" class="info-error" role="alert" hidden></p>
        <ul id="comments-list" class="cmt-list"></ul>
    </div>
</aside>
