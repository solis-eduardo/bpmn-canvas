<x-layouts.app :title="__('messages.notification_preferences_heading')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.notification_preferences_heading') }}</h1>

    @if (session('status'))
        <p class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700" role="status">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('account.notifications.update') }}" class="mt-6 max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        @error('email_notifications_enabled')
            <p class="mb-4 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror

        <label for="email_notifications_enabled" class="flex items-start gap-3">
            <input
                type="checkbox"
                id="email_notifications_enabled"
                name="email_notifications_enabled"
                value="1"
                @checked($emailNotificationsEnabled)
                class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            >
            <span>
                <span class="block font-medium text-gray-900">{{ __('messages.email_notifications') }}</span>
                <span class="mt-1 block text-sm text-gray-600">
                    {{ __('messages.email_notifications_description', ['email' => auth()->user()->email]) }}
                </span>
            </span>
        </label>

        <button
            type="submit"
            class="inline-flex items-center gap-1.5 mt-6 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
            <x-icon name="save" />
            {{ __('messages.save') }}
        </button>
    </form>
</x-layouts.app>
