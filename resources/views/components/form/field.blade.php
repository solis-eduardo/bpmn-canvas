@props(['name', 'label', 'id' => null, 'type' => 'text', 'hint' => null])
@php
    $id ??= $name;
@endphp
{{-- A labelled text-like input: visible <label>, the shared input styling and
     the inline error from components/form/input. Extra attributes (value,
     required, autocomplete...) go to the <input>. --}}
<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700">{{ $label }}</label>
    <x-form.input
        :type="$type"
        :id="$id"
        :name="$name"
        {{ $attributes->merge(['class' => 'rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500']) }}
    />
    @if ($hint)
        <span class="text-xs text-gray-500">{{ $hint }}</span>
    @endif
</div>
