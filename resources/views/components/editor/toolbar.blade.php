@props(['backHref', 'title' => null])

<header>
    <x-button :href="$backHref" id="btn-back-to-list" variant="secondary" size="sm">
        <x-icon name="arrow-left" />
        {{ __('messages.back') }}
    </x-button>
    <span class="brand" id="editor-brand">{{ $title }}</span>
    <span class="spacer"></span>
    {{ $slot }}
    <span class="dirty" id="dirty" title="{{ __('messages.unsaved_changes') }}">●</span>
    <button type="button" class="primary" id="btn-save" title="Ctrl+S"><x-icon name="save" class="inline h-4 w-4 align-text-bottom" /> {{ __('messages.save') }}</button>
</header>
