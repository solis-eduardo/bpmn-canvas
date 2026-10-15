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

            <x-form.actions />
        </x-form.card>

        @can('delete', $diagram)
            {{-- A <details> disclosure keeps the confirmation step JS-free
                 (and inside the CSP): closing it cancels. --}}
            <section class="mt-6 rounded-lg border border-red-200 bg-white p-6 shadow-sm">
                <details>
                    <summary class="cursor-pointer text-sm font-medium text-red-700">{{ __('messages.delete_diagram') }}</summary>
                    <form method="POST" action="{{ route('diagrams.destroy', $diagram->slug) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm text-gray-700">{{ __('messages.delete_diagram_confirm', ['title' => $diagram->title]) }}</p>
                        <x-button type="submit" variant="destructive">
                            <x-icon name="trash" />
                            {{ __('messages.delete_diagram_confirm_button') }}
                        </x-button>
                    </form>
                </details>
            </section>
        @endcan
    </x-form.page>
</x-layouts.app>
