<x-layouts.app :title="__('messages.create_diagram')">
    <x-form.page :title="__('messages.create_diagram')" :back-href="route('diagrams.index')">
        <x-form.card :action="route('diagrams.store')">
            <x-form.field name="title" :label="__('messages.diagram_name')" value="{{ old('title') }}" scope="diagram-create" required />
            <x-form.actions icon="plus" label="{{ __('messages.create_diagram') }}" />
        </x-form.card>
    </x-form.page>
</x-layouts.app>
