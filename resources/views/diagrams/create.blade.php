<x-layouts.app :title="__('messages.create_diagram')">
    <x-form.page :title="__('messages.create_diagram')" :back-href="route('diagrams.index')" :back-label="__('messages.back_to_diagrams')">
        <x-form.card :action="route('diagrams.store')">
            <x-form.field name="title" :label="__('messages.diagram_name')" value="{{ old('title') }}" scope="diagram-create" required />
            <x-button type="submit">
                <x-icon name="plus" />
                {{ __('messages.create_diagram') }}
            </x-button>
        </x-form.card>
    </x-form.page>
</x-layouts.app>
