<x-layouts.app :title="__('messages.edit_workspace')">
    <a href="{{ route('workspaces.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">&larr; {{ __('messages.back_to_workspaces') }}</a>

    <h1 class="mt-4 text-2xl font-semibold text-gray-900">{{ __('messages.edit_workspace') }}</h1>

    <form method="POST" action="{{ route('workspaces.update', $workspace) }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        @csrf
        @method('PUT')
        <x-form.input
            type="text"
            name="name"
            value="{{ old('name', $workspace->name) }}"
            placeholder="{{ __('messages.workspace_name') }}"
            scope="workspace-edit"
            wrapper-class="flex flex-col gap-1 sm:flex-1"
            required
            class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
        <x-button type="submit">
            {{ __('messages.save') }}
        </x-button>
    </form>

    @if ($errors->has('user_id') || $errors->has('email'))
        <ul class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach (array_merge($errors->get('user_id'), $errors->get('email')) as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @if (auth()->user()->is_admin)
        <section class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.keycloak_group') }}</h2>
            <form method="POST" action="{{ route('workspaces.keycloak-group.update', $workspace) }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                @csrf
                @method('PUT')
                <x-form.input
                    id="keycloak-group"
                    type="text"
                    name="keycloak_group"
                    scope="workspace-keycloak"
                    wrapper-class="flex flex-col gap-1 sm:flex-1"
                    value="{{ old('keycloak_group', $workspace->keycloak_group) }}"
                    placeholder="{{ __('messages.keycloak_group_placeholder') }}"
                    aria-label="{{ __('messages.keycloak_group') }}"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <x-button type="submit" variant="secondary">
                    <x-icon name="check" />
                    {{ __('messages.update_keycloak_group') }}
                </x-button>
            </form>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.members') }}</h2>

        <ul class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white px-4">
            @forelse ($workspace->members as $member)
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="text-sm text-gray-900">{{ $member->user->name }} ({{ $member->user->email }})</span>

                    @if (! $workspace->isOwnedBy($member->user))
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

        <form method="POST" action="{{ route('workspaces.members.store', $workspace) }}" class="mt-3 flex items-center gap-2">
            @csrf
            <x-user-select
                :options="$memberCandidates"
                :label="__('messages.select_member')"
                :search-placeholder="__('messages.search_users')"
            />
            <x-button type="submit" variant="secondary" size="sm">
                <x-icon name="user-plus" />
                {{ __('messages.add_member') }}
            </x-button>
        </form>
    </section>

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.transfer_ownership') }}</h2>

        <form method="POST" action="{{ route('workspaces.owner.transfer', $workspace) }}" class="mt-3 flex items-center gap-2">
            @csrf
            @method('PUT')
            <x-user-select
                :options="$ownerCandidates"
                :label="__('messages.select_new_owner')"
                :search-placeholder="__('messages.search_users')"
            />
            <x-button type="submit" variant="secondary" size="sm">
                <x-icon name="transfer" />
                {{ __('messages.transfer_ownership') }}
            </x-button>
        </form>
    </section>
</x-layouts.app>
