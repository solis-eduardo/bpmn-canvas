@props(['action', 'method' => 'POST', 'heading' => null])
{{-- The highlighted form panel: CSRF and method spoofing handled here.
     An optional `heading` titles a secondary card on a multi-form page. --}}
<section class="mt-6">
    @if ($heading)
        <h2 class="mb-3 text-lg font-semibold text-gray-900">{{ $heading }}</h2>
    @endif
    <form method="POST" action="{{ $action }}" {{ $attributes->class('space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm') }}>
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        {{ $slot }}
    </form>
</section>
