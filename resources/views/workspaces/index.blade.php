<x-layouts.app :title="__('messages.workspaces')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.workspaces') }}</h1>
    </div>

    <form method="POST" action="{{ route('workspaces.store') }}" class="mt-6 flex flex-wrap items-center gap-3">
        @csrf
        <x-text-input
            type="text"
            name="name"
            placeholder="{{ __('messages.workspace_name') }}"
            required
        />
        <x-button>{{ __('messages.create_workspace') }}</x-button>
    </form>

    <ul class="mt-8 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm">
        @forelse ($workspaces as $workspace)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <a href="{{ route('workspaces.show', $workspace) }}" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">
                    {{ $workspace->name }}
                </a>

                <span class="text-sm text-gray-500">
                    {{ __('messages.workspace_owner_label', ['name' => $workspace->owner->name]) }}
                </span>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('messages.no_workspaces_yet') }}</li>
        @endforelse
    </ul>
</x-layouts.app>
