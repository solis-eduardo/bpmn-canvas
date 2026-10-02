@props(['name', 'scope' => null, 'wrapperClass' => 'contents'])

@php
    // `scope` ties the error to one form on pages that repeat the same field
    // name across several forms: it round-trips through old('_form').
    $invalid = $errors->has($name) && ($scope === null || old('_form') === $scope);
    $errorId = ($attributes->get('id') ?? $name).'-'.($scope ?? 'field').'-error';
@endphp

<div class="{{ $wrapperClass }}">
    @if ($scope)
        <input type="hidden" name="_form" value="{{ $scope }}">
    @endif
    <input
        name="{{ $name }}"
        {{ $attributes }}
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
    >
    @if ($invalid)
        <p id="{{ $errorId }}" role="alert" class="text-sm text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
