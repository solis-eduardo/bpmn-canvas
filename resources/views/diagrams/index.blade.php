<x-layouts.app :title="__('messages.diagrams')">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.diagrams') }}</h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                {{ __('messages.log_out') }}
            </button>
        </form>
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
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
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
            class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
        >
            {{ __('messages.apply_filters') }}
        </button>
    </form>

    <ul class="mt-8 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm">
        @forelse ($diagrams as $diagram)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <a href="{{ route('diagrams.show', $diagram->slug) }}" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">
                    {{ $diagram->title }}
                </a>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span>{{ __('messages.author_label', ['name' => $diagram->author->name]) }}</span>
                    <span>{{ __('messages.diagram_owner_label', ['name' => $diagram->owner->name]) }}</span>
                    <span title="{{ $diagram->updated_at }}">
                        {{ __('messages.last_modified_label', ['time' => $diagram->updated_at->diffForHumans()]) }}
                    </span>
                    @if ($diagram->hasActiveLock())
                        <span class="text-amber-600">{{ __('messages.being_edited_by', ['name' => $diagram->lockedBy->name]) }}</span>
                    @endif
                </div>

                <div class="flex w-full flex-wrap items-center gap-1.5">
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
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">
                {{ ($search !== '' || $tag !== '' || in_array(true, $filters, true)) ? __('messages.no_diagrams_match_search') : __('messages.no_diagrams_yet') }}
            </li>
        @endforelse
    </ul>

    <div class="mt-4">
        {{ $diagrams->links() }}
    </div>
</x-layouts.app>
