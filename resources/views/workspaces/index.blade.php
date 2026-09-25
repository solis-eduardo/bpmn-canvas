<x-layouts.app :title="__('messages.workspaces')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.workspaces') }}</h1>
    </div>

    <form method="POST" action="{{ route('workspaces.store') }}" class="mt-6 flex flex-wrap items-center gap-3">
        @csrf
        <input
            type="text"
            name="name"
            placeholder="{{ __('messages.workspace_name') }}"
            required
            class="min-w-[240px] flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
        <button
            type="submit"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
            {{ __('messages.create_workspace') }}
        </button>
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
