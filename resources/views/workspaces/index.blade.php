<x-layouts.app :title="__('messages.workspaces_heading')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.workspaces_heading') }}</h1>
    </div>

    <form method="POST" action="{{ route('workspaces.store') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        @csrf
        <input
            type="text"
            name="name"
            placeholder="{{ __('messages.workspace_name') }}"
            required
            class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:flex-1"
        >
        <x-button type="submit">
            <x-icon name="plus" />
            {{ __('messages.create_workspace') }}
        </x-button>
    </form>

    @if ($errors->any())
        <ul class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mt-8 flex flex-col gap-6">
        @forelse ($workspaces as $workspace)
            @php $canManage = auth()->user()->is_admin || $workspace->isOwnedBy(auth()->user()); @endphp
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <p class="font-medium text-gray-900">{{ $workspace->name }}</p>
                        <p class="text-sm text-gray-600">{{ __('messages.workspace_owner_label', ['name' => $workspace->owner->name]) }}</p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        @if ($canManage)
                            <form method="POST" action="{{ route('workspaces.update', $workspace) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ $workspace->name }}"
                                    required
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                <x-button type="submit" variant="secondary" size="sm">
                                    <x-icon name="pencil" />
                                    {{ __('messages.rename') }}
                                </x-button>
                            </form>

                            <form method="POST" action="{{ route('workspaces.owner.transfer', $workspace) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input
                                    type="email"
                                    name="email"
                                    placeholder="{{ __('messages.new_owner_email') }}"
                                    required
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
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
                </div>

                @if (auth()->user()->is_admin)
                    <form method="POST" action="{{ route('workspaces.keycloak-group.update', $workspace) }}" class="flex flex-col gap-2 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:px-6">
                        @csrf
                        @method('PUT')
                        <label for="keycloak-group-{{ $workspace->id }}" class="text-sm font-medium text-gray-700">
                            {{ __('messages.keycloak_group') }}
                        </label>
                        <input
                            id="keycloak-group-{{ $workspace->id }}"
                            type="text"
                            name="keycloak_group"
                            value="{{ $workspace->keycloak_group }}"
                            placeholder="{{ __('messages.keycloak_group_placeholder') }}"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:flex-1"
                        >
                        <x-button type="submit" variant="secondary" size="sm">
                            <x-icon name="check" />
                            {{ __('messages.update_keycloak_group') }}
                        </x-button>
                    </form>
                @endif

                <div class="px-4 py-4 sm:px-6">
                    <p class="text-sm font-medium text-gray-500">{{ __('messages.members') }}</p>

                    <ul class="mt-2 divide-y divide-gray-100">
                        @forelse ($workspace->members as $member)
                            <li class="flex items-center justify-between gap-3 py-2">
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
                            <li class="py-2 text-sm text-gray-500">{{ __('messages.no_members_yet') }}</li>
                        @endforelse
                    </ul>

                    @if ($canManage)
                        <form method="POST" action="{{ route('workspaces.members.store', $workspace) }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <input
                                type="email"
                                name="email"
                                placeholder="{{ __('messages.member_email') }}"
                                required
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:flex-1"
                            >
                            <x-button type="submit" variant="secondary" size="sm">
                                <x-icon name="user-plus" />
                                {{ __('messages.add_member') }}
                            </x-button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center shadow-sm sm:px-6">
                <p class="text-sm text-gray-500">{{ __('messages.no_workspaces_yet') }}</p>
            </div>
        @endforelse
    </div>
</x-layouts.app>
