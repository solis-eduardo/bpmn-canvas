{{-- Table header that sorts its listing: a link that toggles the direction of
     `column` (see App\Repositories\ListSort), keeping every other query
     parameter and restarting pagination. `column` must be on the listing's
     sort allowlist. --}}
@props(['column', 'sort', 'class' => 'px-4 py-3'])

@php
    $active = $sort->isActive($column);
    $url = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $sort->nextDirection($column), 'page' => null]);
    $icon = $active ? ($sort->direction === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevron-up-down';
@endphp

<th
    scope="col"
    @if ($active) aria-sort="{{ $sort->direction === 'asc' ? 'ascending' : 'descending' }}" @endif
    {{ $attributes->class([$class]) }}
>
    <a href="{{ $url }}" data-sort-column="{{ $column }}" class="inline-flex items-center gap-1 hover:text-gray-900 {{ $active ? 'text-gray-900' : '' }}">
        {{ $slot }}
        <x-icon :name="$icon" class="h-3.5 w-3.5 {{ $active ? '' : 'text-gray-400' }}" />
    </a>
</th>
