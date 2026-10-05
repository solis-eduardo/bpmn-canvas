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

            <x-user-select
                name="members"
                :options="$memberOptions"
                :selected="old('members', $memberIds->all())"
                multiple
                :label="__('messages.members')"
                :placeholder="__('messages.select_user_placeholder')"
            />

            <x-user-select
                :options="$ownerCandidates"
                :required="false"
                :label="__('messages.transfer_ownership')"
                :placeholder="__('messages.select_new_owner')"
            />

            <x-form.actions />
        </x-form.card>
    </x-form.page>
</x-layouts.app>
