@props(['name' => 'user_id', 'options', 'label', 'searchPlaceholder'])

<div data-user-select class="flex flex-col gap-1 sm:flex-1">
    <input
        type="search"
        data-user-select-filter
        autocomplete="off"
        aria-label="{{ $searchPlaceholder }}"
        placeholder="{{ $searchPlaceholder }}"
        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
    >
    <select
        name="{{ $name }}"
        required
        aria-label="{{ $label }}"
        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
    >
        <option value="">{{ $label }}</option>
        @foreach ($options as $option)
            <option value="{{ $option['id'] }}">{{ $option['name'] }} ({{ $option['email'] }})</option>
        @endforeach
    </select>
</div>
