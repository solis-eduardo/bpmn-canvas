<x-layouts.app :title="__('messages.edit_user')">
    <div class="mx-auto max-w-xl">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('messages.edit_user') }} — {{ $user->name }}</h1>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700">
                <x-icon name="arrow-left" />
                {{ __('messages.back_to_users') }}
            </a>
        </div>

        @if ($errors->any())
            <ul class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium text-gray-700">{{ __('messages.name') }}</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    required
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium text-gray-700">{{ __('messages.email') }}</label>
                <input
                    type="email"
                    id="email"
                    value="{{ $user->email }}"
                    readonly
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                >
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-sm font-medium text-gray-700">{{ __('messages.login_method') }}</span>
                <span class="text-sm text-gray-600">
                    {{ $user->keycloak_id ? __('messages.login_method_keycloak') : __('messages.login_method_local') }}
                    @if ($user->keycloak_id)
                        <span class="ml-1">({{ $user->keycloak_id }})</span>
                    @endif
                </span>
            </div>
            @unless ($user->keycloak_id)
                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-medium text-gray-700">{{ __('messages.set_new_password') }}</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                    <span class="text-xs text-gray-500">{{ __('messages.leave_blank_to_keep_password') }}</span>
                </div>
            @endunless
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
            >
                <x-icon name="save" />
                {{ __('messages.save') }}
            </button>
        </form>
    </div>
</x-layouts.app>
