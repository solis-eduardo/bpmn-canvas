<x-layouts.app :title="__('messages.edit_diagram_tags').': '.$diagram->title">
    <x-form.page :title="$diagram->title" :back-href="route('diagrams.show', $diagram->slug)">
        <section class="mt-6">
            <h2 class="mb-3 text-lg font-semibold text-gray-900">{{ __('messages.diagram_tags_heading') }}</h2>
            <ul class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm" id="diagram-tags">
                @forelse ($diagram->tags as $diagramTag)
                    <li class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-700">
                        {{ $diagramTag->name }}
                        <form method="POST" action="{{ route('diagrams.tags.destroy', [$diagram->slug, $diagramTag->id]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-gray-400 hover:text-red-600" aria-label="{{ __('messages.remove_tag') }}: {{ $diagramTag->name }}"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                        </form>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">{{ __('messages.no_diagram_tags_yet') }}</li>
                @endforelse
            </ul>
        </section>

        <x-form.card :action="route('diagrams.tags.store', $diagram->slug)" class="sm:flex sm:items-start sm:gap-3 sm:space-y-0">
            <x-tag-select
                :options="$tagNames"
                scope="diagram-tag-{{ $diagram->id }}"
                wrapper-class="min-w-0 flex-1"
                placeholder="{{ __('messages.add_tag_placeholder') }}"
            />
            <x-button type="submit">
                <x-icon name="tag" />
                {{ __('messages.add_tag') }}
            </x-button>
        </x-form.card>
    </x-form.page>
</x-layouts.app>
