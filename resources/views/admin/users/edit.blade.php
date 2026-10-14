<x-layouts.app :title="__('messages.edit_user')">
    <x-form.page :title="__('messages.edit_user').': '.$user->name" :back-href="route('admin.users.index')">
        <x-form.card :action="route('admin.users.update', $user)" method="PUT">
            <x-form.field name="name" :label="__('messages.name')" value="{{ old('name', $user->name) }}" required />
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
                <label for="login_method" class="text-sm font-medium text-gray-700">{{ __('messages.login_method') }}</label>
                <select
                    id="login_method"
                    name="login_method"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    @if ($keycloakConfigured)
                        <option value="local" @selected(old('login_method', $user->login_method) === 'local')>{{ __('messages.login_method_local') }}</option>
                        <option value="keycloak" @selected(old('login_method', $user->login_method) === 'keycloak')>{{ __('messages.login_method_keycloak') }}</option>
                    @else
                        {{-- Without Keycloak the option can't be chosen; a keycloak User's current method is shown but never submitted. --}}
                        @if ($user->usesKeycloak())
                            <option value="" selected disabled>{{ __('messages.login_method_keycloak') }}</option>
                        @endif
                        <option value="local" @selected(! $user->usesKeycloak())>{{ __('messages.login_method_local') }}</option>
                    @endif
                </select>
                @if ($user->keycloak_id)
                    <span class="text-xs text-gray-500">{{ __('messages.login_method_keycloak') }}: {{ $user->keycloak_id }}</span>
                @endif
            </div>
            <x-form.field name="password" type="password" :label="__('messages.set_new_password')" :hint="__('messages.leave_blank_to_keep_password')" autocomplete="new-password" />
            <x-form.actions />
        </x-form.card>

        <section class="mt-10 max-w-3xl space-y-6" aria-labelledby="user-status-heading">
            <h2 id="user-status-heading" class="text-lg font-semibold text-gray-900">{{ __('messages.user_status') }}</h2>

            @error('deactivated')
                <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror

            @if ($user->isDeactivated())
                <p class="text-sm text-gray-600">{{ __('messages.deactivated_badge') }}: <x-timestamp :at="$user->deactivated_at" /></p>

                <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                    @csrf
                    <x-button type="submit" variant="secondary">{{ __('messages.reactivate_user') }}</x-button>
                </form>

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('messages.owned_items_heading') }}</h3>

                    @if ($ownedDiagrams->isEmpty() && $ownedWorkspaces->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('messages.no_owned_items') }}</p>
                    @else
                        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700">
                            @foreach ($ownedDiagrams as $diagram)
                                <li>{{ $diagram->title }}</li>
                            @endforeach
                            @foreach ($ownedWorkspaces as $workspace)
                                <li>{{ $workspace->name }}</li>
                            @endforeach
                        </ul>

                        <form method="POST" action="{{ route('admin.users.transfer-ownership', $user) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex flex-col gap-1">
                                <label for="successor_id" class="text-sm font-medium text-gray-700">{{ __('messages.transfer_ownership_to') }}</label>
                                <select id="successor_id" name="successor_id" required class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    @foreach ($successors as $successor)
                                        <option value="{{ $successor->id }}">{{ $successor->name }}</option>
                                    @endforeach
                                </select>
                                @error('successor_id')
                                    <span class="text-xs text-red-600">{{ $message }}</span>
                                @enderror
                            </div>
                            <x-button type="submit" variant="secondary">{{ __('messages.transfer_ownership_action') }}</x-button>
                        </form>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-600">{{ __('messages.deactivate_user_help') }}</p>

                <form method="POST" action="{{ route('admin.users.deactivate', $user) }}">
                    @csrf
                    <x-button type="submit" variant="destructive">{{ __('messages.deactivate_user') }}</x-button>
                </form>
            @endif
        </section>
    </x-form.page>
</x-layouts.app>
