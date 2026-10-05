<x-layouts.app :title="__('messages.diagrams')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.diagrams') }}</h1>
        <x-button :href="route('diagrams.create')">
            <x-icon name="plus" />
            {{ __('messages.create_diagram') }}
        </x-button>
    </div>

    <form method="GET" action="{{ route('diagrams.index') }}" class="mt-6 flex flex-wrap items-center gap-3">
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="h-4 w-4 pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('messages.search_diagrams_placeholder') }}"
                aria-label="{{ __('messages.search_diagrams_placeholder') }}"
                class="w-full rounded-md border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        </div>

        <x-tag-select
            name="tags[]"
            :options="$tagNames"
            :selected="$selectedTags"
            multiple
            :creatable="false"
            wrapper-class="min-w-48 flex-1"
            placeholder="{{ __('messages.filter_tag') }}"
        />

        <label class="flex items-center gap-1.5 text-sm text-gray-700">
            <input type="checkbox" name="collaborator" value="1" @checked($filters['collaborator'])>
            {{ __('messages.filter_collaborator') }}
        </label>

        <div class="flex w-full flex-wrap items-center gap-3">
            @foreach (['owner_id' => ['filter_diagram_owner', $ownerId], 'author_id' => ['filter_author', $authorId]] as $field => [$labelKey, $selectedId])
                <x-filter-select
                    :name="$field"
                    :options="collect($users)->pluck('name', 'id')->all()"
                    :selected="$selectedId"
                    :label="__('messages.'.$labelKey)"
                />
            @endforeach

            <x-filter-select
                name="workspace"
                :options="['none' => __('messages.no_workspace')] + $workspaces->pluck('name', 'id')->all()"
                :selected="$workspaceFilter === 'all' ? null : $workspaceFilter"
                :label="__('messages.workspace_filter_label')"
            />

            <x-button type="submit" variant="secondary">
                <x-icon name="funnel" />
                {{ __('messages.apply_filters') }}
            </x-button>
        </div>
    </form>

    <div class="mt-8 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm xl:-mx-14">
        <table id="diagram-search-results" class="min-w-[1100px] w-full divide-y divide-gray-200 text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <x-sortable-th column="title" :sort="$sort">{{ __('messages.diagram_column_title') }}</x-sortable-th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_author') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_owner') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.workspace_filter_label') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_lock') }}</th>
                    <th scope="col" class="px-4 py-3 text-center">{{ __('messages.diagram_column_tags') }}</th>
                    <x-sortable-th column="updated_at" :sort="$sort">{{ __('messages.diagram_column_modified') }}</x-sortable-th>
                    <th scope="col" class="w-px whitespace-nowrap px-4 py-3">{{ __('messages.diagram_column_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
        @forelse ($diagrams as $diagram)
            @if ($diagram->is_discoverable)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $diagram->title }}</td>
                <td class="px-4 py-3 text-gray-500">—</td>
                <td class="px-4 py-3 text-gray-600">{{ $diagram->owner->name }}</td>
                <td colspan="4" class="px-4 py-3 text-gray-500">{{ __('messages.discoverable_diagram') }}</td>
                <td class="w-px whitespace-nowrap px-4 py-3">
                    @if ($diagram->collaborators->first()?->status === \App\Models\Collaborator::STATUS_REQUESTED)
                        <span class="text-sm text-gray-500">{{ __('messages.access_request_pending') }}</span>
                    @else
                        <form
                            method="POST"
                            action="{{ route('diagrams.collaborators.store', ['slug' => $diagram->slug] + request()->except(['page', 'discovery_page'])) }}"
                        >
                            @csrf
                            <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                            <button type="submit" class="rounded-md border border-blue-600 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50">
                                {{ __('messages.request_access') }}
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
            @else
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $diagram->title }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $diagram->author->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $diagram->owner->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $diagram->workspace?->name ?? __('messages.no_workspace') }}</td>
                <td class="px-4 py-3">
                    @if ($diagram->hasActiveLock())
                        <span class="text-amber-600">{{ $diagram->lockedBy->name }}</span>
                    @else
                        <span class="text-gray-500">{{ __('messages.not_locked') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex min-w-40 flex-wrap items-center justify-center gap-1.5">
                    @foreach ($diagram->tags as $diagramTag)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $diagramTag->name }}</span>
                    @endforeach
                    </div>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-600"><x-timestamp :at="$diagram->updated_at" /></td>
                <td class="w-px whitespace-nowrap px-4 py-3">
                    <div class="flex flex-nowrap items-center gap-2">
                        <x-button :href="route('diagrams.show', $diagram->slug)" variant="secondary" size="sm">
                            <x-icon name="arrow-right" />
                            {{ __('messages.open_diagram') }}
                        </x-button>
                    </div>
                </td>
            </tr>
            @endif
        @empty
            <tr>
                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                {{ ($search !== '' || $selectedTags !== [] || $ownerId !== null || $authorId !== null || in_array(true, $filters, true)) ? __('messages.no_diagrams_match_search') : __('messages.no_diagrams_yet') }}
                </td>
            </tr>
        @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $diagrams->links() }}
    </div>

    @if (session('status'))
        <p class="mt-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700" role="status">
            {{ session('status') }}
        </p>
    @endif

</x-layouts.app>
