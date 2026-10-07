@if (session('status'))
    <p {{ $attributes->merge(['class' => 'flex items-center gap-2 rounded-md border border-green-600 bg-green-100 px-4 py-3 text-sm font-semibold text-green-900 shadow-sm', 'role' => 'status']) }}>
        <x-icon name="check" class="h-5 w-5 shrink-0 text-green-700" />
        {{ session('status') }}
    </p>
@endif
