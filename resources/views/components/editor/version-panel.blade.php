<aside id="version-panel" hidden>
    <div class="aside-head">
        <span>{{ \App\Support\Ui::text('messages.versions') }}</span>
        <button type="button" id="btn-history-close" data-panel-close{!! \App\Support\Ui::attr('messages.close', 'aria-label') !!}><x-icon name="x-mark" /></button>
    </div>
    <p class="version-hint">{{ \App\Support\Ui::text('messages.select_two_versions_hint') }}</p>
    <ul id="version-list"></ul>
    <div class="version-compare-bar">
        <span id="version-compare-count">0/2</span>
        <button type="button" id="btn-compare-selected" disabled><x-icon name="transfer" class="inline h-4 w-4 align-text-bottom" /> {{ \App\Support\Ui::text('messages.compare_selected') }}</button>
    </div>
</aside>
