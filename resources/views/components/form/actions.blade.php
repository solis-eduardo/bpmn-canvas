@props(['icon' => 'save', 'label' => null, 'exit' => true])
{{-- Submit row of a form card: the primary button saves and stays on the form,
     the secondary "Save and exit" (submit_action=exit) returns to the listing.
     See Concerns\RedirectsAfterSave. --}}
<div class="flex flex-wrap items-center justify-center gap-3 pt-2">
    @if ($exit)
        <x-button type="submit" variant="secondary" name="submit_action" value="exit">
            <x-icon name="arrow-right" />
            {{ __('messages.save_and_exit') }}
        </x-button>
    @endif
    <x-button type="submit" name="submit_action" value="save">
        <x-icon :name="$icon" />
        {{ $label ?? __('messages.save') }}
    </x-button>
</div>
