<x-layouts.app :title="__('messages.diagrams')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.diagrams') }}</h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                {{ __('messages.log_out') }}
            </button>
        </form>
    </div>

    <form method="POST" action="{{ route('diagrams.store') }}" class="mt-6 flex flex-wrap items-center gap-3">
        @csrf
        <input
            type="text"
            name="title"
            placeholder="{{ __('messages.diagram_name') }}"
            required
            class="min-w-[240px] flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
        <button
            type="submit"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
            {{ __('messages.create_diagram') }}
        </button>
    </form>

    <ul class="mt-8 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm">
        @forelse ($diagrams as $diagram)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <a href="{{ route('diagrams.show', $diagram->slug) }}" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">
                    {{ $diagram->title }}
                </a>

                <div class="flex items-center gap-3 text-sm text-gray-500">
                    <span>{{ __('messages.author_label', ['name' => $diagram->author->name]) }}</span>
                    @if ($diagram->hasActiveLock())
                        <span class="text-amber-600">{{ __('messages.being_edited_by', ['name' => $diagram->lockedBy->name]) }}</span>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('messages.no_diagrams_yet') }}</li>
        @endforelse
    </ul>
</x-layouts.app>
