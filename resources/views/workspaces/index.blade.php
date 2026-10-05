<x-layouts.app :title="__('messages.workspaces_heading')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.workspaces_heading') }}</h1>
        <x-button :href="route('workspaces.create')">
            <x-icon name="plus" />
            {{ __('messages.create_workspace') }}
        </x-button>
    </div>

    @if ($errors->any())
        <ul class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="GET" action="{{ route('workspaces.index') }}" class="mt-6 flex flex-wrap items-center gap-3">
        <div class="relative w-full max-w-sm">
            <x-icon name="search" class="h-4 w-4 pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                name="name"
                value="{{ $filters->name }}"
                placeholder="{{ __('messages.workspace_name') }}"
                aria-label="{{ __('messages.workspace_name') }}"
                class="w-full rounded-md border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        </div>

        @foreach (['owner' => 'filter_workspace_owner', 'member' => 'filter_workspace_member'] as $filterKey => $filterLabel)
            <x-filter-select
                :name="$filterKey"
                :options="collect($users)->mapWithKeys(fn ($user) => [$user['id'] => $user['name'].' ('.$user['email'].')'])->all()"
                :selected="$filters->{$filterKey}"
                :label="__('messages.'.$filterLabel)"
            />
        @endforeach

        <x-button type="submit" variant="secondary">
            <x-icon name="funnel" />
            {{ __('messages.apply_filters') }}
        </x-button>
    </form>

    <div class="mt-8 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-[720px] w-full divide-y divide-gray-200 text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <x-sortable-th column="name" :sort="$sort">{{ __('messages.workspace_column_name') }}</x-sortable-th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.workspace_column_owner') }}</th>
                    <x-sortable-th column="members_count" :sort="$sort">{{ __('messages.members') }}</x-sortable-th>
                    <x-sortable-th column="updated_at" :sort="$sort">{{ __('messages.diagram_column_modified') }}</x-sortable-th>
                    <th scope="col" class="w-px whitespace-nowrap px-4 py-3">{{ __('messages.workspace_column_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white align-top">
            @forelse ($workspaces as $workspace)
                @php $canManage = auth()->user()->is_admin || $workspace->isOwnedBy(auth()->user()); @endphp
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $workspace->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $workspace->owner->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $workspace->members_count }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-600"><x-timestamp :at="$workspace->updated_at" /></td>
                    <td class="w-px whitespace-nowrap px-4 py-3">
                        <div class="flex flex-nowrap items-center gap-2">
                            @if ($canManage)
                                <x-button :href="route('workspaces.edit', $workspace)" variant="secondary" size="sm">
                                    <x-icon name="pencil" />
                                    {{ __('messages.edit_workspace_link') }}
                                </x-button>

                                <form method="POST" action="{{ route('workspaces.destroy', $workspace) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="destructive" size="sm">
                                        <x-icon name="trash" />
                                        {{ __('messages.delete_workspace') }}
                                    </x-button>
                                </form>
                            @endif

                            @if ($workspace->is_member && ! $workspace->isOwnedBy(auth()->user()))
                                <form method="POST" action="{{ route('workspaces.leave', $workspace) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="destructive" size="sm">
                                        <x-icon name="logout" />
                                        {{ __('messages.leave_workspace') }}
                                    </x-button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                        {{ $filters->isActive() ? __('messages.no_workspaces_match_filters') : __('messages.no_workspaces_yet') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $workspaces->links() }}
    </div>
</x-layouts.app>
