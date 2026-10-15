@props(['entries', 'filters', 'action'])
{{-- Filter form and table of activity log entries (CONTEXT.md, "Activity Log"), the
     Administrators' global log (the Diagram's Activity panel is public/assets/activity.js). Every value is escaped. --}}
@php
    $fieldClass = 'rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
@endphp

<form method="GET" action="{{ $action }}" class="mt-6 flex flex-wrap items-center gap-3">
    <select name="action" aria-label="{{ __('messages.activity_column_action') }}" class="w-64 {{ $fieldClass }}">
        <option value="">{{ __('messages.activity_all_actions') }}</option>
        @foreach (\App\Models\ActivityLog::ACTIONS as $name)
            <option value="{{ $name }}" @selected($filters->action === $name)>{{ __('messages.activity_action_'.str_replace('.', '_', $name)) }}</option>
        @endforeach
    </select>
    <input type="search" name="actor" value="{{ $filters->actor }}" placeholder="{{ __('messages.activity_column_actor') }}" aria-label="{{ __('messages.activity_column_actor') }}" class="w-48 {{ $fieldClass }}">
    <input type="search" name="diagram" value="{{ $filters->diagram }}" placeholder="{{ __('messages.activity_column_diagram') }}" aria-label="{{ __('messages.activity_column_diagram') }}" class="w-48 {{ $fieldClass }}">
    <input type="date" name="from" value="{{ request('from') }}" aria-label="{{ __('messages.activity_filter_from') }}" title="{{ __('messages.activity_filter_from') }}" class="{{ $fieldClass }}">
    <input type="date" name="to" value="{{ request('to') }}" aria-label="{{ __('messages.activity_filter_to') }}" title="{{ __('messages.activity_filter_to') }}" class="{{ $fieldClass }}">
    <x-button type="submit" variant="secondary">
        <x-icon name="funnel" />
        {{ __('messages.apply_filters') }}
    </x-button>
</form>

<div class="mt-8 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="whitespace-nowrap bg-gray-50">
            <tr>
                @foreach (['when', 'actor', 'action', 'diagram', 'subject', 'changes'] as $column)
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('messages.activity_column_'.$column) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @forelse ($entries as $entry)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600"><x-timestamp :at="$entry->created_at" /></td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">{{ $entry->actor_name ?? __('messages.activity_system') }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">{{ __('messages.activity_action_'.str_replace('.', '_', $entry->action)) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $entry->diagram_title ?? $entry->workspace_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $entry->subject_label ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        @foreach ($entry->changes(auth()->user()) as $change)
                            <div>
                                <span class="font-medium">{{ $change['field'] }}:</span>
                                {{ $change['before'] }}{{ $change['before'] !== null && $change['after'] !== null ? ' → ' : '' }}{{ $change['after'] }}
                            </div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">{{ $filters->isActive() ? __('messages.activity_no_match') : __('messages.activity_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $entries->links() }}
</div>
