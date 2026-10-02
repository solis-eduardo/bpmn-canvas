<x-layouts.app :title="__('messages.create_local_account')">
    <div class="mx-auto max-w-xl">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.create_local_account') }}</h1>
            <x-button :href="route('admin.users.index')" variant="secondary" size="sm">
                &larr; {{ __('messages.back_to_users') }}
            </x-button>
        </div>

        @if ($errors->any())
            <ul class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium text-gray-700">{{ __('messages.name') }}</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium text-gray-700">{{ __('messages.email') }}</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>
            <div class="flex flex-col gap-1">
                <label for="password" class="text-sm font-medium text-gray-700">{{ __('messages.password') }}</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>
            <x-button type="submit">
                {{ __('messages.create_local_account') }}
            </x-button>
        </form>
    </div>
</x-layouts.app>
