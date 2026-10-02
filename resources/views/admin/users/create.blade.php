<x-layouts.app :title="__('messages.create_user')">
    <x-form.page :title="__('messages.create_user')" :back-href="route('admin.users.index')" :back-label="__('messages.back_to_users')">
        <x-form.card :action="route('admin.users.store')">
            <x-form.field name="name" :label="__('messages.name')" value="{{ old('name') }}" required />
            <x-form.field name="email" type="email" :label="__('messages.email')" value="{{ old('email') }}" required />
            <x-form.field name="password" type="password" :label="__('messages.password')" autocomplete="new-password" required />
            <x-button type="submit">
                <x-icon name="user-plus" />
                {{ __('messages.create_user') }}
            </x-button>
        </x-form.card>
    </x-form.page>
</x-layouts.app>
