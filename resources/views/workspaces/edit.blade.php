<x-layouts.app :title="__('messages.edit_workspace')">
    <x-form.page :title="__('messages.edit_workspace')" :back-href="route('workspaces.index')">
        <x-form.card :action="route('workspaces.update', $workspace)" method="PUT">
            <x-form.field name="name" :label="__('messages.workspace_name')" value="{{ old('name', $workspace->name) }}" required />
            @can('manageKeycloakGroup', $workspace)
                <x-form.field
                    name="keycloak_group"
                    :label="__('messages.keycloak_group')"
                    :hint="__('messages.keycloak_group_placeholder')"
                    value="{{ old('keycloak_group', $workspace->keycloak_group) }}"
                />
            @endcan
            <x-form.actions />
        </x-form.card>

        <section class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.members') }}</h2>
            <ul class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white px-4 shadow-sm">
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
            <x-form.card :action="route('workspaces.members.store', $workspace)" class="sm:flex sm:items-end sm:gap-3 sm:space-y-0">
                <x-user-select
                    :options="$memberCandidates"
                    scope="add-member"
                    :label="__('messages.select_member')"
                    :placeholder="__('messages.select_user_placeholder')"
                />
                <x-button type="submit">
                    <x-icon name="user-plus" />
                    {{ __('messages.add_member') }}
                </x-button>
            </x-form.card>
        </section>

        <x-form.card :action="route('workspaces.owner.transfer', $workspace)" method="PUT" :heading="__('messages.transfer_ownership')" class="sm:flex sm:items-end sm:gap-3 sm:space-y-0">
            <x-user-select
                :options="$ownerCandidates"
                scope="transfer-owner"
                :label="__('messages.select_new_owner')"
                :placeholder="__('messages.select_user_placeholder')"
            />
            <x-button type="submit">
                <x-icon name="transfer" />
                {{ __('messages.transfer_ownership') }}
            </x-button>
        </x-form.card>
    </x-form.page>
</x-layouts.app>
