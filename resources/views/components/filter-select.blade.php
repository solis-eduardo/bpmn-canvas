@props(['name', 'options', 'selected' => null, 'label', 'clearable' => true])

{{-- Searchable single-choice filter (Tom Select, see resources/js/app.js).
     `options` maps each submitted value to its label; the empty option is the
     placeholder and, when `clearable`, the control offers a clear button that
     submits it. Keeps the plain <select>, so the query string is unchanged. --}}
<div class="flex w-56 flex-col gap-0.5">
    <select
        name="{{ $name }}"
        data-tag-select
        data-no-create
        @if ($clearable) data-clearable @endif
        data-placeholder="{{ $label }}"
        aria-label="{{ $label }}"
    >
        @if ($clearable)
            <option value=""></option>
        @endif
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected((string) $value === (string) $selected)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
