@props(['title', 'backHref' => null])
{{-- Shell shared by the form screens (the User form is the reference): a
     narrow column with the title, an optional Back button, the flash status
     and the form cards below it (see components/form/card). --}}
<div class="mx-auto max-w-xl">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>
        @if ($backHref)
            <x-button :href="$backHref" variant="secondary" size="sm">
                <x-icon name="arrow-left" />
                {{ __('messages.back') }}
            </x-button>
        @endif
    </div>
    <x-flash-status class="mt-6" />
    {{ $slot }}
</div>
