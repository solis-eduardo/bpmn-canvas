<x-layouts.app :title="__('messages.backups_heading')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.backups_heading') }}</h1>

    <dl class="mt-6 divide-y divide-gray-200 rounded-md border border-gray-200 bg-white">
        @foreach ($rows as $row)
            <div class="px-4 py-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <dt class="text-sm font-medium text-gray-700">{{ $row['label'] }}</dt>
                    <dd class="text-sm text-gray-900">
                        @if ($row['success'])
                            <x-timestamp :at="$row['success']->created_at" />
                        @else
                            {{ __('messages.backup_never') }}
                        @endif
                    </dd>
                </div>
                @if ($row['failure'])
                    <p class="mt-2 text-sm text-red-700">
                        {{ __('messages.backup_last_failure') }}:
                        <x-timestamp :at="$row['failure']->created_at" />
                        — {{ $row['failure']->message }}
                    </p>
                @endif
            </div>
        @endforeach
    </dl>
</x-layouts.app>
