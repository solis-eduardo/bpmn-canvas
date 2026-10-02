<x-layouts.app :title="__('messages.home')">
    <div class="space-y-8">
        <section>
            <h1 class="text-2xl font-semibold text-gray-900">
                {{ __('messages.home_greeting', ['name' => auth()->user()->name]) }}
            </h1>
        </section>

        <section aria-labelledby="home-recent-diagrams-heading">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 id="home-recent-diagrams-heading" class="text-xl font-semibold text-gray-900">
                    {{ __('messages.home_recent_diagrams') }}
                </h2>
                <a
                    href="{{ route('diagrams.index') }}"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                >
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
            <a href="{{ route('workspaces.index') }}" class="rounded-lg border border-gray-200 bg-white p-4 font-medium text-blue-700 hover:bg-gray-50">
                {{ __('messages.home_workspace_shortcut') }}
            </a>
            <a href="{{ route('notifications.history') }}" class="rounded-lg border border-gray-200 bg-white p-4 font-medium text-blue-700 hover:bg-gray-50">
                {{ __('messages.home_notifications_shortcut') }}
            </a>
            <a href="{{ route('diagrams.index') }}" class="rounded-lg border border-gray-200 bg-white p-4 font-medium text-blue-700 hover:bg-gray-50">
                {{ __('messages.home_search_shortcut') }}
            </a>
        </nav>
    </div>
</x-layouts.app>
