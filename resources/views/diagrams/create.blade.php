<x-layouts.app :title="__('messages.create_diagram')">
    <a href="{{ route('diagrams.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">&larr; {{ __('messages.back_to_diagrams') }}</a>

    <h1 class="mt-4 text-2xl font-semibold text-gray-900">{{ __('messages.create_diagram') }}</h1>

    <form method="POST" action="{{ route('diagrams.store') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        @csrf
        <x-form.input
            type="text"
            name="title"
            value="{{ old('title') }}"
            scope="diagram-create"
            wrapper-class="flex flex-col gap-1 sm:flex-1"
            placeholder="{{ __('messages.diagram_name') }}"
            required
            class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
        <x-button type="submit">
            {{ __('messages.create_diagram') }}
        </x-button>
    </form>
</x-layouts.app>
