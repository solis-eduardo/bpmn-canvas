{{-- A timestamp in the app's one listing date format (locale-aware date and
     time in the signed-in User's timezone), with the relative time as its tooltip. Renders nothing for null. --}}
@props(['at'])

@if (\App\Support\Ui::standalone())
    {{-- The standalone page fills it from the stored date (standalone/main.js). --}}
    <time data-timestamp {{ $attributes }}></time>
@elseif ($at)
    <time datetime="{{ $at->toISOString() }}" title="{{ $at->diffForHumans() }}" {{ $attributes }}>{{ (auth()->user()?->inDisplayTimezone($at) ?? $at)->isoFormat('L LT') }}</time>
@endif
