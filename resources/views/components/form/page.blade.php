@props(['title', 'backHref' => null, 'backLabel' => null])
{{-- Shell shared by the form screens (the User form is the reference): a
     narrow column with the title, an optional Back button and the form
     cards below it (see components/form/card). --}}
<div class="mx-auto max-w-xl">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>
        @if ($backHref)
            <x-button :href="$backHref" variant="secondary" size="sm">
                <x-icon name="arrow-left" />
                {{ $backLabel }}
            </x-button>
        @endif
    </div>
    {{ $slot }}
</div>
