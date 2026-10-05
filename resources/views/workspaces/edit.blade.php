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

        <x-form.card :action="route('workspaces.members.sync', $workspace)" method="PUT" :heading="__('messages.members')">
            <x-user-select
                name="members"
                :options="$memberOptions"
                :selected="$memberIds->all()"
                multiple
                scope="members"
                :label="__('messages.members')"
                :placeholder="__('messages.select_user_placeholder')"
            />
            <x-button type="submit">
                <x-icon name="user-plus" />
                {{ __('messages.save_members') }}
            </x-button>
        </x-form.card>

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
