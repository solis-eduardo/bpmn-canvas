<x-layouts.app :title="__('messages.create_diagram')">
    <x-form.page :title="__('messages.create_diagram')" :back-href="route('diagrams.index')">
        <x-form.card :action="route('diagrams.store')">
            <x-form.field name="title" :label="__('messages.diagram_name')" value="{{ old('title') }}" scope="diagram-create" required />

            @if ($templates->isNotEmpty())
                <div class="flex flex-col gap-1">
                    <label for="template_id" class="text-sm font-medium text-gray-700">{{ __('messages.template_label') }}</label>
                    <select
                        id="template_id"
                        name="template_id"
                        data-tag-select
                        data-no-create
                        data-clearable
                        data-placeholder="{{ __('messages.no_template') }}"
                        aria-label="{{ __('messages.template_label') }}"
                        @error('template_id') aria-invalid="true" aria-describedby="template_id-error" @enderror
                    >
                        <option value=""></option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected((string) old('template_id') === (string) $template->id)>{{ $template->title }}</option>
                        @endforeach
                    </select>
                    @error('template_id')
                        <p id="template_id-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="flex flex-col gap-1">
                <label for="workspace_id" class="text-sm font-medium text-gray-700">{{ __('messages.workspace_filter_label') }}</label>
                <select
                    id="workspace_id"
                    name="workspace_id"
                    data-tag-select
                    data-no-create
                    data-clearable
                    data-placeholder="{{ __('messages.no_workspace') }}"
                    aria-label="{{ __('messages.workspace_filter_label') }}"
                    @error('workspace_id') aria-invalid="true" aria-describedby="workspace_id-error" @enderror
                >
                    <option value=""></option>
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

            <x-user-select
                name="collaborators"
                :options="$collaboratorOptions"
                :selected="array_map('intval', (array) old('collaborators', []))"
                multiple
                :label="__('messages.collaborators')"
                :placeholder="__('messages.select_user_placeholder')"
            />

            <x-form.actions icon="plus" label="{{ __('messages.create_diagram') }}" />
        </x-form.card>
    </x-form.page>
</x-layouts.app>
