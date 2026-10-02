<x-layouts.app :title="__('messages.new_workspace')">
    <x-form.page :title="__('messages.new_workspace')" :back-href="route('workspaces.index')">
        <x-form.card :action="route('workspaces.store')">
            <x-form.field name="name" :label="__('messages.workspace_name')" value="{{ old('name') }}" required />
            @can('manageKeycloakGroup', new \App\Models\Workspace)
                <x-form.field
                    name="keycloak_group"
                    :label="__('messages.keycloak_group')"
                    :hint="__('messages.keycloak_group_placeholder')"
                    value="{{ old('keycloak_group') }}"
                />
            @endcan
            <x-form.actions icon="plus" label="{{ __('messages.create_workspace') }}" />
        </x-form.card>
    </x-form.page>
</x-layouts.app>
