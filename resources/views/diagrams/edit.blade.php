@php
    $canEdit = auth()->user()->can('update', $diagram);
@endphp
<x-layouts.app :title="__('messages.edit_diagram').': '.$diagram->title">
    <x-form.page :title="$diagram->title" :back-href="route('diagrams.show', $diagram->slug)">
        <x-form.card :action="route('diagrams.settings.update', $diagram->slug)" method="PUT">
            @if ($canEdit)
                <x-form.field name="title" :label="__('messages.diagram_column_title')" value="{{ old('title', $diagram->title) }}" maxlength="255" required />
            @endif

            @can('diagram-owner.transfer', $diagram)
                <x-user-select
                    name="user_id"
                    :options="$ownerCandidates"
                    :required="false"
                    :label="__('messages.transfer_ownership')"
                    :placeholder="__('messages.select_new_owner')"
                />
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="give_up_access" value="1" @checked(old('give_up_access')) class="rounded border-gray-300">
                    {{ __('messages.give_up_access') }}
                </label>
            @endcan

            @if ($canEdit)
                <x-tag-select
                    name="tags[]"
                    :options="$tagNames"
                    :selected="old('tags', $diagram->tags->pluck('name')->all())"
                    multiple
                    wrapper-class="w-full"
                    placeholder="{{ __('messages.add_tag_placeholder') }}"
                />
            @endif

            @can('manageTemplate', $diagram)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_template" value="0">
                    <input type="checkbox" name="is_template" value="1" @checked(old('is_template', $diagram->is_template)) class="rounded border-gray-300">
                    {{ __('messages.diagram_is_template') }}
                </label>
                <p class="-mt-3 text-xs text-gray-500">{{ __('messages.diagram_is_template_hint') }}</p>
            @endcan

            <x-form.actions />
        </x-form.card>
    </x-form.page>
</x-layouts.app>
