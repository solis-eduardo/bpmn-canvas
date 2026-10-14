@if (\App\Support\Demo::enabled())
    <div role="status" class="shrink-0 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-amber-100 px-4 py-2 text-sm text-amber-900" data-demo-banner>
        <span>{{ __('messages.demo_banner') }}</span>
        <form method="POST" action="{{ route('demo.reset') }}">
            @csrf
            <button type="submit" class="font-medium underline hover:no-underline">{{ __('messages.demo_reset') }}</button>
        </form>
    </div>
@endif
