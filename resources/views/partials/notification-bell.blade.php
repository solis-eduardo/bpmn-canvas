{{-- The bell (issue #15): unread count polled from /notifications/unread-count,
     dropdown populated from /notifications. Self-contained (no dependency on
     common.js/editor.js, since this renders on every screen) — see
     assets/notifications.js. --}}
<div
    id="notification-bell"
    class="relative"
    data-unread-count-url="{{ route('notifications.unread-count') }}"
    data-list-url="{{ route('notifications.index') }}"
    data-read-all-url="{{ route('notifications.read-all') }}"
    data-empty-notice="{{ __('messages.no_notifications_yet') }}"
    data-mark-all-read-label="{{ __('messages.mark_all_as_read') }}"
>
    <button
        type="button"
        id="btn-notification-bell"
        class="relative flex h-8 w-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900"
        title="{{ __('messages.notifications') }}"
        aria-label="{{ __('messages.notifications') }}"
        aria-haspopup="true"
        aria-expanded="false"
    >
        <x-icon name="bell" class="h-5 w-5" />
        <span
            id="notification-unread-badge"
            class="absolute -right-1 -top-1 hidden min-w-[1.1rem] rounded-full bg-red-600 px-1 text-center text-[0.65rem] font-semibold leading-[1.1rem] text-white"
        ></span>
    </button>

    <div
        id="notification-dropdown"
        hidden
        class="absolute right-0 z-20 mt-2 w-80 max-w-[90vw] rounded-md border border-gray-200 bg-white py-2 shadow-lg"
    >
        <div class="flex items-center justify-between px-3 pb-2">
            <span class="text-sm font-semibold text-gray-900">{{ __('messages.notifications') }}</span>
            <button type="button" id="btn-notification-mark-all-read" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                {{ __('messages.mark_all_as_read') }}
            </button>
        </div>
        <ul id="notification-list" class="max-h-96 divide-y divide-gray-100 overflow-y-auto"></ul>

        <div class="border-t border-gray-100 px-3 pt-2 text-center">
            <a href="{{ route('notifications.history') }}" id="link-notification-history" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                {{ __('messages.view_all_notifications') }}
            </a>
        </div>
    </div>
</div>
