<x-layouts.app :title="__('messages.home')">
    <div class="space-y-8">
        <section>
            <h1 class="text-2xl font-semibold text-gray-900">
                {{ __('messages.home_greeting', ['name' => auth()->user()->name]) }}
            </h1>
        </section>

        <form method="GET" action="{{ route('home') }}" role="search" class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-0 flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="{{ __('messages.search_placeholder') }}"
                    aria-label="{{ __('messages.search_placeholder') }}"
                    class="w-full rounded-md border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>
            <x-button type="submit" variant="secondary">
                <x-icon name="search" />
                {{ __('messages.search_button') }}
            </x-button>
        </form>

        @if ($results !== null)
            <section aria-labelledby="home-search-results-heading" class="space-y-6">
                <h2 id="home-search-results-heading" class="text-xl font-semibold text-gray-900">
                    {{ __('messages.search_results_heading') }}
                </h2>

                @php
                    $groups = [
                        ['key' => 'diagrams', 'label' => 'search_diagrams', 'all' => route('diagrams.index', ['q' => $search])],
                        ['key' => 'workspaces', 'label' => 'search_workspaces', 'all' => route('workspaces.index', ['name' => $search])],
                        ['key' => 'notifications', 'label' => 'search_notifications', 'all' => route('notifications.history', ['q' => $search])],
                    ];
                @endphp

                @foreach ($groups as $group)
                    @php($result = $results[$group['key']])
                    <div data-search-group="{{ $group['key'] }}">
                        <div class="mb-2 flex items-center justify-between gap-4">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('messages.'.$group['label']) }}</h3>
                            @if ($result['total'] > count($result['items']))
                                <a href="{{ $group['all'] }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">
                                    {{ __('messages.search_see_all', ['count' => $result['total']]) }}
                                </a>
                            @endif
                        </div>

                        @if (count($result['items']) === 0)
                            <p class="rounded-lg border border-gray-200 bg-white px-4 py-4 text-sm text-gray-600">{{ __('messages.search_no_results') }}</p>
                        @else
                                <ul class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white">
                                    @foreach ($result['items'] as $item)
                                        <li class="px-4 py-3">
                                            @if ($group['key'] === 'diagrams')
                                                <a
                                                    href="{{ $item->is_discoverable ? route('diagrams.index', ['q' => $item->title]) : route('diagrams.show', $item->slug) }}"
                                                    class="font-medium text-blue-700 hover:text-blue-900"
                                                >{{ $item->title }}</a>
                                            @elseif ($group['key'] === 'workspaces')
                                                <a href="{{ route('workspaces.index', ['name' => $item->name]) }}" class="font-medium text-blue-700 hover:text-blue-900">{{ $item->name }}</a>
                                            @else
                                                <a href="{{ $item['url'] }}" class="font-medium text-blue-700 hover:text-blue-900">{{ $item['message'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </section>
        @else
        <section aria-labelledby="home-recent-diagrams-heading">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 id="home-recent-diagrams-heading" class="text-xl font-semibold text-gray-900">
                    {{ __('messages.home_recent_diagrams') }}
                </h2>
                <a
                    href="{{ route('diagrams.index') }}"
                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                >
                    <x-icon name="plus" />
                    {{ __('messages.create_diagram') }}
                </a>
            </div>

            @if ($diagrams->isEmpty())
                <p class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-sm text-gray-600">
                    {{ __('messages.home_no_recent_diagrams') }}
                </p>
            @else
                <ul class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white">
                    @foreach ($diagrams as $diagram)
                        <li class="flex items-center justify-between gap-4 px-4 py-3">
                            <a
                                href="{{ route('diagrams.show', $diagram->slug) }}"
                                class="font-medium text-blue-700 hover:text-blue-900"
                            >
                                {{ $diagram->title }}
                            </a>
                            <time datetime="{{ $diagram->updated_at->toISOString() }}" class="shrink-0 text-sm text-gray-500">
                                {{ $diagram->updated_at->diffForHumans() }}
                            </time>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <nav aria-label="{{ __('messages.home_shortcuts') }}" class="grid gap-3 sm:grid-cols-3">
            <a href="{{ route('workspaces.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-4 font-medium text-blue-700 hover:bg-gray-50">
                <x-icon name="workspaces" />
                {{ __('messages.home_workspace_shortcut') }}
            </a>
            <a href="{{ route('notifications.history') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-4 font-medium text-blue-700 hover:bg-gray-50">
                <x-icon name="bell" />
                {{ __('messages.home_notifications_shortcut') }}
            </a>
            <a href="{{ route('diagrams.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-4 font-medium text-blue-700 hover:bg-gray-50">
                <x-icon name="search" />
                {{ __('messages.home_search_shortcut') }}
            </a>
        </nav>
        @endif
    </div>
</x-layouts.app>
