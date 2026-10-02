@props(['name' => 'name', 'options', 'scope' => null, 'placeholder'])

@php
    // Same error contract as components/form/input: `scope` ties the error to
    // one form on pages that repeat the field across several forms.
    $invalid = $errors->has($name) && ($scope === null || old('_form') === $scope);
    $errorId = $name.'-'.($scope ?? 'field').'-error';
@endphp

{{-- Tag picker (Tom Select, see resources/js/app.js): pick an existing Tag or
     create one by typing. Reusable by the list filters. --}}
<div class="flex min-w-36 flex-col gap-0.5">
    @if ($scope)
        <input type="hidden" name="_form" value="{{ $scope }}">
    @endif
    <select
        name="{{ $name }}"
        data-tag-select
        data-placeholder="{{ $placeholder }}"
        aria-label="{{ $placeholder }}"
        {{ $attributes }}
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
    >
        <option value=""></option>
        @foreach ($options as $option)
            <option value="{{ $option }}">{{ $option }}</option>
        @endforeach
    </select>
    @if ($invalid)
        <p id="{{ $errorId }}" role="alert" class="text-sm text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
