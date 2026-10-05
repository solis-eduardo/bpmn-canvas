<x-layouts.app :title="__('messages.create_diagram')">
    <x-form.page :title="__('messages.create_diagram')" :back-href="route('diagrams.index')">
        <x-form.card :action="route('diagrams.store')">
            <x-form.field name="title" :label="__('messages.diagram_name')" value="{{ old('title') }}" scope="diagram-create" required />

            <div class="flex flex-col gap-1">
                <label for="workspace_id" class="text-sm font-medium text-gray-700">{{ __('messages.workspace_filter_label') }}</label>
                <select
                    id="workspace_id"
                    name="workspace_id"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    @error('workspace_id') aria-invalid="true" aria-describedby="workspace_id-error" @enderror
                >
                    <option value="">{{ __('messages.no_workspace') }}</option>
                    @foreach ($workspaces as $workspace)
                        <option value="{{ $workspace->id }}" @selected((string) old('workspace_id') === (string) $workspace->id)>{{ $workspace->name }}</option>
                    @endforeach
                </select>
                @error('workspace_id')
                    <p id="workspace_id-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="tags" class="text-sm font-medium text-gray-700">{{ __('messages.diagram_column_tags') }}</label>
                <x-tag-select
                    name="tags[]"
                    id="tags"
                    :options="collect($tagNames)->merge((array) old('tags', []))->unique()->values()->all()"
                    :selected="(array) old('tags', [])"
                    multiple
                    wrapper-class="min-w-0"
                    placeholder="{{ __('messages.add_tag_placeholder') }}"
                />
            </div>

            <x-form.actions icon="plus" label="{{ __('messages.create_diagram') }}" />
        </x-form.card>
    </x-form.page>
</x-layouts.app>
