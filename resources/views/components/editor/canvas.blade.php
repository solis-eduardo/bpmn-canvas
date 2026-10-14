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
