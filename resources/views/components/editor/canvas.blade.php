<main>
    <div id="canvas"></div>
    <div class="zoom">
        <button type="button" id="zoom-in"{!! \App\Support\Ui::attr('messages.zoom_in') !!}><x-icon name="plus" /></button>
        <button type="button" id="zoom-out"{!! \App\Support\Ui::attr('messages.zoom_out') !!}><x-icon name="minus" /></button>
        <button type="button" id="zoom-fit"{!! \App\Support\Ui::attr('messages.fit_to_screen') !!}><x-icon name="fit" /></button>
    </div>
    <div class="diff-banner" id="diff-banner" hidden>
        <strong id="diff-banner-label"></strong>
        <span class="legend"><span class="swatch added"></span>{{ \App\Support\Ui::text('messages.diff_added') }}</span>
        <span class="legend"><span class="swatch changed"></span>{{ \App\Support\Ui::text('messages.diff_changed') }}</span>
        <span class="legend"><span class="swatch removed"></span>{{ \App\Support\Ui::text('messages.diff_removed') }}</span>
        <button type="button" id="btn-diff-exit"><x-icon name="x-mark" class="inline h-4 w-4 align-text-bottom" /> {{ \App\Support\Ui::text('messages.exit_comparison') }}</button>
    </div>
    <div class="diff-removed-panel" id="diff-removed-panel" hidden>
        <div class="head">{{ \App\Support\Ui::text('messages.diff_removed_elements') }}</div>
        <ul id="diff-removed-list"></ul>
    </div>
</main>
