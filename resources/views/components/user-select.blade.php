@props(['name' => 'user_id', 'options', 'label', 'placeholder', 'scope' => null, 'multiple' => false, 'selected' => [], 'required' => true])
@php
    // Same error contract as components/form/input: `scope` ties the error to
    // one form on pages that repeat the field across several forms.
    $id = ($scope ?? $name).'-'.$name;
    // A multi-select submits `name[]`; its errors key on `name` or `name.N`.
    $errorKey = $multiple ? collect($errors->keys())->first(fn ($key) => $key === $name || str_starts_with($key, $name.'.')) : $name;
    $invalid = $errorKey !== null && $errors->has($errorKey) && ($scope === null || old('_form') === $scope);
    $errorId = $id.'-error';
@endphp
{{-- Picker over existing Users (Tom Select, see resources/js/app.js): searchable
     by name, never creates a new entry. Single choice, or with `multiple`
     removable chips for the `selected` user ids. Options carry no emails
     (issue #233). --}}
<div class="flex flex-col gap-1 sm:flex-1">
    @if ($scope)
        <input type="hidden" name="_form" value="{{ $scope }}">
    @endif
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700">{{ $label }}</label>
    <select
        id="{{ $id }}"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        data-tag-select
        data-no-create
        data-placeholder="{{ $placeholder }}"
        @if ($multiple) multiple @elseif ($required) required @endif
        aria-label="{{ $label }}"
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
    >
        @unless ($multiple)
            <option value=""></option>
        @endunless
        @foreach ($options as $option)
            <option value="{{ $option['id'] }}"{{ in_array($option['id'], $selected) ? ' selected' : '' }}>{{ $option['name'] }}</option>
        @endforeach
    </select>
    @if ($invalid)
        <p id="{{ $errorId }}" role="alert" class="text-sm text-red-600">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
