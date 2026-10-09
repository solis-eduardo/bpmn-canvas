{{-- A timestamp in the app's one listing date format (locale-aware date and
     time in the signed-in User's timezone), with the relative time as its tooltip. Renders nothing for null. --}}
@props(['at'])
@php($tz = auth()->user()?->displayTimezone() ?? config('app.timezone'))

@if ($at)
    <time datetime="{{ $at->toISOString() }}" title="{{ $at->diffForHumans() }}" {{ $attributes }}>{{ $at->copy()->timezone($tz)->isoFormat('L LT') }}</time>
@endif
