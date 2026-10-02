@props(['name' => 'user_id', 'options', 'label', 'placeholder', 'scope' => null])
@php
    // Same error contract as components/form/input: `scope` ties the error to
    // one form on pages that repeat the field across several forms.
    $id = ($scope ?? $name).'-'.$name;
    $invalid = $errors->has($name) && ($scope === null || old('_form') === $scope);
    $errorId = $id.'-error';
@endphp
{{-- Picker over existing Users (Tom Select, see resources/js/app.js): single
     choice, searchable by name or email, never creates a new entry. --}}
<div class="flex flex-col gap-1 sm:flex-1">
    @if ($scope)
        <input type="hidden" name="_form" value="{{ $scope }}">
    @endif
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700">{{ $label }}</label>
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        data-tag-select
        data-no-create
        data-placeholder="{{ $placeholder }}"
        required
        aria-label="{{ $label }}"
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
    >
        <option value=""></option>
        @foreach ($options as $option)
            <option value="{{ $option['id'] }}">{{ $option['name'] }} ({{ $option['email'] }})</option>
        @endforeach
    </select>
    @if ($invalid)
        <p id="{{ $errorId }}" role="alert" class="text-sm text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
