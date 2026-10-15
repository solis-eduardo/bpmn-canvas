<x-layouts.app :title="__('messages.activity').': '.$diagram->title">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.activity') }}: {{ $diagram->title }}</h1>
        <x-button :href="route('diagrams.show', $diagram->slug)" variant="secondary" size="sm">
            <x-icon name="arrow-left" />
            {{ __('messages.back') }}
        </x-button>
    </div>

    <x-activity-log :entries="$entries" :filters="$filters" :action="route('diagrams.activity', $diagram->slug)" />
</x-layouts.app>
