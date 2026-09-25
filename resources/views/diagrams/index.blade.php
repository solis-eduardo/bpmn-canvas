<x-layouts.app :title="__('messages.diagrams')">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold text-gray-900">{{ __('messages.diagrams') }}</h1>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                    {{ __('messages.log_out') }}
                </button>
            </form>
        </div>

        <!-- Create Diagram Form -->
        <form method="POST" action="{{ route('diagrams.store') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <input
                    type="text"
                    name="title"
                    placeholder="{{ __('messages.diagram_name') }}"
                    required
                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0"
                >
            </div>
            <button
                type="submit"
                class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto"
            >
                {{ __('messages.create_diagram') }}
            </button>
        </form>

        <!-- Diagrams Table -->
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @forelse ($diagrams as $diagram)
                <div class="border-b border-gray-200 transition-colors hover:bg-gray-50 last:border-b-0">
                    <div class="grid grid-cols-1 gap-4 px-4 py-4 sm:grid-cols-2 md:grid-cols-4 md:gap-0">
                        <!-- Title -->
                        <div class="flex flex-col md:px-4 md:py-4">
                            <span class="text-xs font-medium text-gray-500 md:hidden">{{ __('messages.title') }}</span>
                            <a href="{{ route('diagrams.show', $diagram->slug) }}" class="font-semibold text-blue-600 hover:text-blue-700 hover:underline">
                                {{ $diagram->title }}
                            </a>
                        </div>

                        <!-- Author -->
                        <div class="flex flex-col md:px-4 md:py-4">
                            <span class="text-xs font-medium text-gray-500 md:hidden">{{ __('messages.author') }}</span>
                            <span class="text-sm text-gray-600">{{ $diagram->author->name }}</span>
                        </div>

                        <!-- Last Modified -->
                        <div class="flex flex-col md:px-4 md:py-4">
                            <span class="text-xs font-medium text-gray-500 md:hidden">{{ __('messages.last_modified') }}</span>
                            <span class="text-sm text-gray-600" title="{{ $diagram->updated_at->toDateTimeString() }}">
                                {{ $diagram->updated_at->format('M d, Y H:i') }}
                            </span>
                        </div>

                        <!-- Lock Status -->
                        <div class="flex flex-col md:px-4 md:py-4">
                            <span class="text-xs font-medium text-gray-500 md:hidden">{{ __('messages.status') }}</span>
                            @if ($diagram->hasActiveLock())
                                <div class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 w-fit">
                                    <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                                    <span class="text-xs font-medium text-amber-700">
                                        {{ __('messages.being_edited_by', ['name' => $diagram->lockedBy->name]) }}
                                    </span>
                                </div>
                            @else
                                <div class="inline-flex items-center gap-2 w-fit">
                                    <span class="inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                                    <span class="text-xs font-medium text-green-700">{{ __('messages.available') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="text-sm text-gray-500">{{ __('messages.no_diagrams_yet') }}</p>
                    <p class="mt-2 text-xs text-gray-400">{{ __('messages.create_your_first_diagram') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
