<x-layouts.app :title="__('messages.create_workspace')">
    <div class="mx-auto max-w-xl">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.create_workspace') }}</h1>
            <a href="{{ route('workspaces.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                {{ __('messages.back_to_workspaces') }}
            </a>
        </div>

        @if ($errors->any())
            <ul class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('workspaces.store') }}" class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium text-gray-700">{{ __('messages.workspace_name') }}</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>
            <button
                type="submit"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
            >
                {{ __('messages.create_workspace') }}
            </button>
        </form>
    </div>
</x-layouts.app>
