<x-layouts.app :title="__('messages.activity_log_heading')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.activity_log_heading') }}</h1>

    <x-activity-log :entries="$entries" :filters="$filters" :action="route('admin.activity.index')" />
</x-layouts.app>
