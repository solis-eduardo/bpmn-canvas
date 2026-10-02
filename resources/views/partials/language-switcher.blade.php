@php
    $languages = [
        'en' => ['flag' => '🇺🇸', 'name' => 'English'],
        'pt_BR' => ['flag' => '🇧🇷', 'name' => 'Português (Brasil)'],
        'es' => ['flag' => '🇪🇸', 'name' => 'Español'],
    ];
    $current = $languages[app()->getLocale()] ?? $languages['en'];
@endphp
<details class="lang-switch relative" aria-label="{{ __('messages.language') }}">
    <summary
        class="flex cursor-pointer list-none items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 [&::-webkit-details-marker]:hidden"
        aria-label="{{ __('messages.language') }}: {{ $current['name'] }}"
    >
        <span aria-hidden="true">{{ $current['flag'] }}</span>
        <span>{{ $current['name'] }}</span>
    </summary>
    <ul class="absolute right-0 z-20 mt-1 min-w-full whitespace-nowrap rounded-md border border-gray-200 bg-white py-1 shadow-lg" aria-label="{{ __('messages.language') }}">
        @foreach ($languages as $code => $language)
            <li>
                <form method="POST" action="{{ route('locale.update', $code) }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100 {{ app()->getLocale() === $code ? 'font-semibold text-gray-900' : 'text-gray-600' }}"
                        @if (app()->getLocale() === $code) aria-current="true" @endif
                    >
                        <span aria-hidden="true">{{ $language['flag'] }}</span>
                        <span>{{ $language['name'] }}</span>
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</details>
