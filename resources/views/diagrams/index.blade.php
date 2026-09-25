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

    <form method="POST" action="{{ route('diagrams.store') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        @csrf
        <input
            type="text"
            name="title"
            placeholder="{{ __('messages.diagram_name') }}"
            required
            class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:flex-1"
        >
        <button
            type="submit"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
            {{ __('messages.create_diagram') }}
        </button>
    </form>

    <div class="mt-8 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @forelse ($diagrams as $diagram)
            <div class="border-b border-gray-200 px-4 py-4 last:border-b-0 sm:px-6 sm:py-5">
                <div class="space-y-3 sm:space-y-2">
                    <!-- Title and Lock Status -->
                    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
                        <a href="{{ route('diagrams.show', $diagram->slug) }}" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">
                            {{ $diagram->title }}
                        </a>
                        @if ($diagram->hasActiveLock())
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-200">
                                {{ __('messages.being_edited_by', ['name' => $diagram->lockedBy->name]) }}
                            </span>
                        @endif
                    </div>

                    <!-- Metadata -->
                    <div class="flex flex-col gap-2 text-sm text-gray-600 sm:flex-row sm:gap-4">
                        <span>{{ __('messages.author_label', ['name' => $diagram->author->name]) }}</span>
                        <span class="hidden sm:inline">•</span>
                        <span>{{ __('messages.diagram_owner_label', ['name' => $diagram->owner->name]) }}</span>
                        <span class="hidden sm:inline">•</span>
                        <span>{{ $diagram->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center sm:px-6">
                <p class="text-sm text-gray-500">{{ __('messages.no_diagrams_yet') }}</p>
            </div>
        @endforelse
    </div>
</x-layouts.app>
