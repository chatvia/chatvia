<div
    x-data="{ show: false }"
    x-init="show = !localStorage.getItem('chatvia.cookie_consent')"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    class="fixed bottom-0 inset-x-0 z-50 p-4 bg-white border-t border-gray-200 shadow-lg"
    style="display: none"
    role="region"
    aria-label="Cookie notice"
>
    <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3">
        <p class="text-sm text-gray-600 text-center sm:text-start">
            {{ __('landing.cookie_banner.message') }}
            <a href="{{ route('privacy') }}" class="underline hover:text-gray-900 ms-1">{{ __('landing.cookie_banner.privacy') }}</a>
        </p>
        <button
            @click="localStorage.setItem('chatvia.cookie_consent', '1'); show = false"
            class="shrink-0 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 transition-colors"
        >
            {{ __('landing.cookie_banner.accept') }}
        </button>
    </div>
</div>
