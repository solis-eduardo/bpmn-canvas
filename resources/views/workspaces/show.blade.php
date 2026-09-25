@php
    $canManage = auth()->user()->can('manageMembers', $workspace);
@endphp

<x-layouts.app :title="$workspace->name">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ $workspace->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('messages.workspace_owner_label', ['name' => $workspace->owner->name]) }}
            </p>
        </div>

        <a href="{{ route('workspaces.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">
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

    @if ($canManage)
        <form method="POST" action="{{ route('workspaces.update', $workspace) }}" class="mt-6 flex flex-wrap items-center gap-3">
            @csrf
            @method('PUT')
            <input
                type="text"
                name="name"
                value="{{ $workspace->name }}"
                aria-label="{{ __('messages.workspace_name') }}"
                required
                class="min-w-[240px] flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
            <button
                type="submit"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
            >
                {{ __('messages.rename') }}
            </button>
        </form>
    @endif

    <h2 class="mt-8 text-lg font-semibold text-gray-900">{{ __('messages.members') }}</h2>

    @if ($canManage)
        <form method="POST" action="{{ route('workspaces.members.store', $workspace) }}" class="mt-3 flex flex-wrap items-center gap-3">
            @csrf
            <input
                type="email"
                name="email"
                placeholder="{{ __('messages.member_email') }}"
                required
                class="min-w-[240px] flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
            <button
                type="submit"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
            >
                {{ __('messages.add_member') }}
            </button>
        </form>
    @endif

    <ul class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm">
        @forelse ($workspace->members as $member)
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-medium text-gray-900">{{ $member->name }}</p>
                    <p class="text-sm text-gray-500">{{ $member->email }}</p>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    @if ($workspace->isOwnedBy($member))
                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-green-700">
                            {{ __('messages.workspace_owner_badge') }}
                        </span>
                    @elseif ($canManage)
                        <form
                            method="POST"
                            action="{{ route('workspaces.members.destroy', [$workspace, $member]) }}"
                            onsubmit="return confirm('{{ __('messages.remove_member_confirm') }}')"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">
                                {{ __('messages.remove') }}
                            </button>
                        </form>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('messages.no_members_yet') }}</li>
        @endforelse
    </ul>
</x-layouts.app>
