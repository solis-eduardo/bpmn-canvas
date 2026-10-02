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
        @foreach (['name' => 'workspace_name', 'owner' => 'filter_workspace_owner', 'member' => 'filter_workspace_member', 'diagram' => 'filter_workspace_diagram'] as $filterKey => $filterLabel)
            <input
                type="search"
                name="{{ $filterKey }}"
                value="{{ $filters->{$filterKey} }}"
                placeholder="{{ __('messages.'.$filterLabel) }}"
                aria-label="{{ __('messages.'.$filterLabel) }}"
                class="w-48 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        @endforeach

        <x-button type="submit" variant="secondary">
            <x-icon name="funnel" />
            {{ __('messages.apply_filters') }}
        </x-button>
    </form>

    <div class="mt-8 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-[960px] w-full divide-y divide-gray-200 text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ __('messages.workspace_column_name') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.workspace_column_owner') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.members') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.workspace_column_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white align-top">
            @forelse ($workspaces as $workspace)
                @php $canManage = auth()->user()->is_admin || $workspace->isOwnedBy(auth()->user()); @endphp
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-900">
                        {{ $workspace->name }}

                        @if (auth()->user()->is_admin)
                            <form method="POST" action="{{ route('workspaces.keycloak-group.update', $workspace) }}" class="mt-2 flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <label for="keycloak-group-{{ $workspace->id }}" class="text-xs font-medium text-gray-700">
                                    {{ __('messages.keycloak_group') }}
                                </label>
                                <input
                                    id="keycloak-group-{{ $workspace->id }}"
                                    type="text"
                                    name="keycloak_group"
                                    value="{{ $workspace->keycloak_group }}"
                                    placeholder="{{ __('messages.keycloak_group_placeholder') }}"
                                    class="w-36 rounded-md border border-gray-300 px-2 py-1 text-xs font-normal shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                <x-button type="submit" variant="secondary" size="sm">
                                    <x-icon name="check" />
                                    {{ __('messages.update_keycloak_group') }}
                                </x-button>
                            </form>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $workspace->owner->name }}</td>
                    <td class="px-4 py-3">
                        <ul class="divide-y divide-gray-100">
                            @forelse ($workspace->members as $member)
                                <li class="flex items-center justify-between gap-3 py-1">
                                    <span class="text-sm text-gray-900">{{ $member->user->name }} ({{ $member->user->email }})</span>

                                    @if ($canManage && ! $workspace->isOwnedBy($member->user))
                                        <form method="POST" action="{{ route('workspaces.members.destroy', [$workspace, $member->user]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="destructive" size="sm">
                                                <x-icon name="user-minus" />
                                                {{ __('messages.remove_member') }}
                                            </x-button>
                                        </form>
                                    @endif
                                </li>
                            @empty
                                <li class="py-1 text-sm text-gray-500">{{ __('messages.no_members_yet') }}</li>
                            @endforelse
                        </ul>

                        @if ($canManage)
                            <form method="POST" action="{{ route('workspaces.members.store', $workspace) }}" class="mt-2 flex items-center gap-2">
                                @csrf
                                <input
                                    type="email"
                                    name="email"
                                    placeholder="{{ __('messages.member_email') }}"
                                    required
                                    class="rounded-md border border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                <x-button type="submit" variant="secondary" size="sm">
                                    <x-icon name="user-plus" />
                                    {{ __('messages.add_member') }}
                                </x-button>
                            </form>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-col gap-2">
                            @if ($canManage)
                                <x-button :href="route('workspaces.edit', $workspace)" variant="secondary" size="sm">
                                    <x-icon name="pencil" />
                                    {{ __('messages.edit_workspace_link') }}
                                </x-button>

                                <form method="POST" action="{{ route('workspaces.owner.transfer', $workspace) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input
                                        type="email"
                                        name="email"
                                        placeholder="{{ __('messages.new_owner_email') }}"
                                        required
                                        class="rounded-md border border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    >
                                    <x-button type="submit" variant="secondary" size="sm">
                                        <x-icon name="transfer" />
                                        {{ __('messages.transfer_ownership') }}
                                    </x-button>
                                </form>

                                <form method="POST" action="{{ route('workspaces.destroy', $workspace) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="destructive" size="sm">
                                        <x-icon name="trash" />
                                        {{ __('messages.delete_workspace') }}
                                    </x-button>
                                </form>
                            @endif

                            @if ($workspace->members->contains('user_id', auth()->id()) && ! $workspace->isOwnedBy(auth()->user()))
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
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
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
