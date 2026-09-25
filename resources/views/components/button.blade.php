<button {{ $attributes->merge(['type' => 'submit', 'class' => 'rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700']) }}>
    {{ $slot }}
</button>
