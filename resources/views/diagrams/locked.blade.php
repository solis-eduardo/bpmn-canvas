<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagram->title }} — {{ __('messages.being_edited') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    <div class="min-h-full">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-2xl items-center gap-4 px-4 py-3 sm:px-6">
                <a href="{{ url('/diagrams') }}" class="text-lg font-semibold text-gray-900">
                    {{ __('messages.diagrams') }}
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-12 sm:px-6">
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-8">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $diagram->title }}</h1>
                </div>

                <div class="space-y-8 px-6 py-8">
                    <!-- Lock Status Card -->
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-6">
                        <div class="flex items-start gap-4">
                            <div class="mt-1 flex h-10 w-10 items-center justify-center rounded-full bg-blue-200">
                                <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.being_edited') }}</h2>
                                <p class="mt-2 text-base text-gray-700">
                                    <strong>{{ e($lockedBy->name) }}</strong> {{ __('messages.is_currently_editing') }}
                                </p>
                                @if ($lockedAt)
                                    <p class="mt-2 text-sm text-gray-600">
                                        {{ __('messages.locked_since') }}: <time datetime="{{ $lockedAt->toIso8601String() }}" id="lock-time">{{ $lockedAt->diffForHumans() }}</time>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Request Lock Section -->
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-6">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ __('messages.request_lock_title') }}</h3>
                        <p class="mb-6 text-gray-700">{{ __('messages.request_lock_description') }}</p>

                        <div class="space-y-4">
                            <button
                                type="button"
                                id="btn-request-lock"
                                class="w-full rounded-lg bg-indigo-600 px-6 py-3 text-base font-semibold text-white transition-colors hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed"
                                data-diagram-slug="{{ $diagram->slug }}"
                                data-request-lock-url="{{ route('diagrams.lock.request', $diagram->slug) }}"
                            >
                                {{ __('messages.request_lock') }}
                            </button>

                            <div id="countdown-container" class="hidden rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                                <p class="text-center text-sm text-indigo-900">
                                    {{ __('messages.lock_transfer_in') }}: <span id="countdown" class="font-mono font-bold text-lg text-indigo-600">30</span>s
                                </p>
                                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-indigo-200">
                                    <div id="countdown-bar" class="h-full w-full bg-indigo-600 transition-all duration-100" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fork Option -->
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-6">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ __('messages.continue_working') }}</h3>
                        <p class="mb-6 text-gray-700">{{ __('messages.fork_description') }}</p>

                        <form method="POST" action="{{ route('diagrams.fork', $diagram->slug) }}" class="inline-block w-full">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-lg border border-gray-300 bg-white px-6 py-3 text-base font-semibold text-gray-900 transition-colors hover:bg-gray-50 active:bg-gray-100"
                            >
                                {{ __('messages.create_a_copy_to_continue') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnRequestLock = document.getElementById('btn-request-lock');
            const countdownContainer = document.getElementById('countdown-container');
            const countdownElement = document.getElementById('countdown');
            const countdownBar = document.getElementById('countdown-bar');
            const requestLockUrl = btnRequestLock.dataset.requestLockUrl;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            let pollInterval = null;
            let countdownInterval = null;
            let startTime = null;
            const transferSeconds = 30;

            async function requestLock() {
                btnRequestLock.disabled = true;

                try {
                    const response = await fetch(requestLockUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                    });

                    const data = await response.json();

                    if (data.state === 'acquired') {
                        window.location.href = window.location.href;
                    } else if (data.state === 'waiting') {
                        startCountdown(new Date(data.expires_at));
                    }
                } catch (error) {
                    console.error('Error requesting lock:', error);
                    btnRequestLock.disabled = false;
                }
            }

            function startCountdown(expiresAt) {
                countdownContainer.classList.remove('hidden');
                btnRequestLock.classList.add('hidden');
                startTime = new Date().getTime();
                const endTime = expiresAt.getTime();

                function updateCountdown() {
                    const now = new Date().getTime();
                    const remaining = Math.max(0, Math.ceil((endTime - now) / 1000));
                    const percentage = (remaining / transferSeconds) * 100;

                    countdownElement.textContent = remaining;
                    countdownBar.style.width = percentage + '%';

                    if (remaining === 0) {
                        clearInterval(countdownInterval);
                        window.location.href = window.location.href;
                    }
                }

                updateCountdown();
                countdownInterval = setInterval(updateCountdown, 100);
            }

            btnRequestLock.addEventListener('click', requestLock);

            // Update lock time ago every minute
            const lockTimeEl = document.getElementById('lock-time');
            if (lockTimeEl && lockTimeEl.dateTime) {
                setInterval(function() {
                    const date = new Date(lockTimeEl.dateTime);
                    lockTimeEl.textContent = new Date(lockTimeEl.dateTime).toLocaleString();
                }, 60000);
            }
        });
    </script>
</body>
</html>
