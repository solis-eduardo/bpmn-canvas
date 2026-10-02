<x-layouts.app :title="__('messages.notification_history_heading')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.notification_history_heading') }}</h1>
    </div>

    @if ($term !== '')
        <p class="mt-4 text-sm text-gray-600">{{ __('messages.search_results_for', ['term' => $term]) }}</p>
    @endif

    <ul class="mt-8 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm">
        @forelse ($notifications as $notification)
            <li>
                <a
                    href="{{ $notification['url'] }}"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-gray-50 {{ $notification['read'] ? 'text-gray-500' : 'font-medium text-gray-900' }}"
                >
                    <span>{{ $notification['message'] }}</span>

                    <span class="flex items-center gap-2 text-sm text-gray-500" title="{{ $notification['created_at'] }}">
                        @unless ($notification['read'])
                            <span class="h-2 w-2 rounded-full bg-blue-600" aria-hidden="true"></span>
                        @endunless
                        {{ $notification['created_at']?->diffForHumans() }}
                    </span>
                </a>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('messages.no_notifications_yet') }}</li>
        @endforelse
    </ul>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
</x-layouts.app>
