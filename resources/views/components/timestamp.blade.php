{{-- A timestamp in the app's one listing date format (locale-aware date and
     time), with the relative time as its tooltip. Renders nothing for null. --}}
@props(['at'])

@if ($at)
    <time datetime="{{ $at->toISOString() }}" title="{{ $at->diffForHumans() }}" {{ $attributes }}>{{ $at->isoFormat('L LT') }}</time>
@endif
