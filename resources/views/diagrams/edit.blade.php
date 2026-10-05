<x-layouts.app :title="__('messages.edit_diagram_tags').': '.$diagram->title">
    <x-form.page :title="$diagram->title" :back-href="route('diagrams.show', $diagram->slug)">
        <x-form.card :action="route('diagrams.tags.sync', $diagram->slug)" method="PUT" :heading="__('messages.diagram_tags_heading')">
            <x-tag-select
                name="tags[]"
                :options="$tagNames"
                :selected="$diagram->tags->pluck('name')->all()"
                multiple
                scope="diagram-tags-{{ $diagram->id }}"
                placeholder="{{ __('messages.add_tag_placeholder') }}"
            />
            <x-button type="submit">
                <x-icon name="tag" />
                {{ __('messages.save_tags') }}
            </x-button>
        </x-form.card>
    </x-form.page>
</x-layouts.app>
