<x-layouts.app :title="__('messages.diagrams')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.diagrams') }}</h1>
        <x-button :href="route('diagrams.create')">
            <x-icon name="plus" />
            {{ __('messages.create_diagram') }}
        </x-button>
    </div>

    <form method="GET" action="{{ route('diagrams.index') }}" class="mt-4 flex flex-wrap items-center gap-4">
        <div class="relative w-full max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('messages.search_diagrams_placeholder') }}"
                class="w-full rounded-md border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
        </div>

        @foreach (['owner' => 'filter_owner', 'collaborator' => 'filter_collaborator', 'locked' => 'filter_locked', 'recent' => 'filter_recent'] as $filterKey => $filterLabel)
            <label class="flex items-center gap-1.5 text-sm text-gray-700">
                <input type="checkbox" name="{{ $filterKey }}" value="1" @checked($filters[$filterKey])>
                {{ __('messages.'.$filterLabel) }}
            </label>
        @endforeach

        <input
            type="text"
            name="tag"
            value="{{ $tag }}"
            placeholder="{{ __('messages.filter_by_tag_placeholder') }}"
            class="w-40 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >

        <label class="flex items-center gap-1.5 text-sm text-gray-700">
            {{ __('messages.workspace_filter_label') }}
            <select name="workspace" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="all" @selected($workspaceFilter === 'all')>{{ __('messages.all_workspaces') }}</option>
                <option value="none" @selected($workspaceFilter === 'none')>{{ __('messages.no_workspace') }}</option>
                @foreach ($workspaces as $workspace)
                    <option value="{{ $workspace->id }}" @selected((string) $workspace->id === $workspaceFilter)>{{ $workspace->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-1.5 text-sm text-gray-700">
            {{ __('messages.sort_by') }}
            <select name="sort" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="title" @selected($sort === 'title')>{{ __('messages.sort_title') }}</option>
                <option value="updated_at" @selected($sort === 'updated_at')>{{ __('messages.sort_last_modified') }}</option>
            </select>
        </label>
        <select name="direction" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            <option value="asc" @selected($direction === 'asc')>{{ __('messages.sort_ascending') }}</option>
            <option value="desc" @selected($direction === 'desc')>{{ __('messages.sort_descending') }}</option>
        </select>

        <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
        >
            <x-icon name="funnel" />
            {{ __('messages.apply_filters') }}
        </button>
    </form>

    <div class="mt-8 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table id="diagram-search-results" class="min-w-[1040px] w-full divide-y divide-gray-200 text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_title') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_author') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_owner') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.workspace_filter_label') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_modified') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_lock') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_tags') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_actions') }}</th>
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
                <td class="whitespace-nowrap px-4 py-3">
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
                <td class="whitespace-nowrap px-4 py-3 text-gray-600" title="{{ $diagram->updated_at }}">
                    {{ $diagram->updated_at->diffForHumans() }}
                </td>
                <td class="px-4 py-3">
                    @if ($diagram->hasActiveLock())
                        <span class="text-amber-600">{{ __('messages.being_edited_by', ['name' => $diagram->lockedBy->name]) }}</span>
                    @else
                        <span class="text-gray-500">{{ __('messages.not_locked') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex min-w-40 flex-wrap items-center gap-1.5">
                    @php($canManageTags = auth()->user()->can('manageTags', $diagram))

                    @foreach ($diagram->tags as $diagramTag)
                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                            {{ $diagramTag->name }}
                            @if ($canManageTags)
                                <form method="POST" action="{{ route('diagrams.tags.destroy', [$diagram->slug, $diagramTag->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-400 hover:text-red-600" aria-label="{{ __('messages.remove_tag') }}"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                                </form>
                            @endif
                        </span>
                    @endforeach

                    @if ($canManageTags)
                        <form method="POST" action="{{ route('diagrams.tags.store', $diagram->slug) }}" class="flex items-center gap-1">
                            @csrf
                            <x-tag-select
                                :options="$tagNames"
                                scope="diagram-tag-{{ $diagram->id }}"
                                placeholder="{{ __('messages.add_tag_placeholder') }}"
                            />
                            <x-button type="submit" variant="secondary" size="sm"><x-icon name="tag" class="h-3.5 w-3.5" />{{ __('messages.add_tag') }}</x-button>
                        </form>
                    @endif
                    </div>
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <x-button :href="route('diagrams.show', $diagram->slug)" variant="secondary" size="sm">
                        <x-icon name="arrow-right" />
                        {{ __('messages.open_diagram') }}
                    </x-button>
                </td>
            </tr>
            @endif
        @empty
            <tr>
                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                {{ ($search !== '' || $tag !== '' || in_array(true, $filters, true)) ? __('messages.no_diagrams_match_search') : __('messages.no_diagrams_yet') }}
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
