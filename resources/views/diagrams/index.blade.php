<x-layouts.app :title="__('messages.diagrams')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.diagrams') }}</h1>
    </div>

    <form method="POST" action="{{ route('diagrams.store') }}" class="mt-6 flex flex-wrap items-center gap-3">
        @csrf
        <input
            type="text"
            name="title"
            placeholder="{{ __('messages.diagram_name') }}"
            required
            class="min-w-[240px] flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
        <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
            <x-icon name="plus" />
            {{ __('messages.create_diagram') }}
        </button>
    </form>

    <form method="GET" action="{{ route('diagrams.index') }}" class="mt-4 flex flex-wrap items-center gap-4">
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="{{ __('messages.search_diagrams_placeholder') }}"
            class="w-full max-w-sm rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >

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
        <table class="min-w-[1040px] w-full divide-y divide-gray-200 text-left text-sm">
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
                                    <button type="submit" class="text-gray-400 hover:text-red-600" aria-label="{{ __('messages.remove_tag') }}">&times;</button>
                                </form>
                            @endif
                        </span>
                    @endforeach

                    @if ($canManageTags)
                        <form method="POST" action="{{ route('diagrams.tags.store', $diagram->slug) }}" class="flex items-center gap-1">
                            @csrf
                            <input
                                type="text"
                                name="name"
                                placeholder="{{ __('messages.add_tag_placeholder') }}"
                                class="w-28 rounded border border-gray-300 px-1.5 py-0.5 text-xs shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            >
                            <button type="submit" class="text-xs font-medium text-blue-600 hover:underline">{{ __('messages.add_tag') }}</button>
                        </form>
                    @endif
                    </div>
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <a href="{{ route('diagrams.show', $diagram->slug) }}" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">
                        {{ __('messages.open_diagram') }}
                    </a>
                </td>
            </tr>
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

    <section class="mt-12" aria-labelledby="diagram-discovery-heading">
        <h2 id="diagram-discovery-heading" class="text-xl font-semibold text-gray-900">
            {{ __('messages.diagram_discovery_heading') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('messages.diagram_discovery_description') }}</p>

        <form method="GET" action="{{ route('diagrams.index') }}" class="mt-4 flex flex-wrap items-center gap-3">
            <label for="diagram-discovery-search" class="sr-only">{{ __('messages.diagram_discovery_search_label') }}</label>
            <input
                id="diagram-discovery-search"
                type="search"
                name="discover"
                value="{{ $discoverySearch }}"
                placeholder="{{ __('messages.diagram_discovery_search_placeholder') }}"
                class="w-full max-w-sm rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
            <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
            >
                <x-icon name="search" />
                {{ __('messages.search') }}
            </button>
        </form>

        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table id="diagram-discovery-table" class="min-w-[480px] w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_title') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('messages.diagram_column_owner') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('messages.request_access') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
            @forelse ($discoveryDiagrams as $discoveryDiagram)
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $discoveryDiagram->title }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $discoveryDiagram->owner->name }}</td>
                    <td class="px-4 py-3">

                    @if ($discoveryDiagram->collaborators->first()?->status === \App\Models\Collaborator::STATUS_REQUESTED)
                        <span class="text-sm text-gray-500">{{ __('messages.access_request_pending') }}</span>
                    @else
                        <form
                            method="POST"
                            action="{{ route('diagrams.collaborators.store', ['slug' => $discoveryDiagram->slug, 'discover' => $discoverySearch]) }}"
                        >
                            @csrf
                            <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                            <button
                                type="submit"
                                class="rounded-md border border-blue-600 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50"
                            >
                                {{ __('messages.request_access') }}
                            </button>
                        </form>
                    @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                        {{ $discoverySearch !== '' ? __('messages.no_diagrams_match_search') : __('messages.no_discoverable_diagrams') }}
                    </td>
                </tr>
            @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $discoveryDiagrams->links() }}
        </div>
    </section>
</x-layouts.app>
