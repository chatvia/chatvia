@php
    $navigation = [
        ['label' => __('landing.nav.home'), 'link' => LaravelLocalization::localizeUrl('/')],
        ['label' => __('landing.nav.features'), 'link' => LaravelLocalization::localizeUrl('/').'#features'],
        ['label' => __('landing.nav.pricing'), 'link' => LaravelLocalization::localizeUrl('/pricing')],
        ['label' => __('landing.nav.mission'), 'link' => route('mission')],
        ['label' => __('landing.nav.security'), 'link' => route('security')],
    ];

    // Chatters have no dashboard — send them straight to the first agent they
    // can access instead. Resolved once here so both the desktop and mobile
    // menus reuse it; null for guests and non-chatters.
    $authUser = auth()->user();
    $isChatter = $authUser?->isChatter() ?? false;
    $chatterAgent = $isChatter ? $authUser->listAccessibleAgents()->first() : null;

    // Other supported locales (exclude the active one) for the language switcher.
    $currentLocale = LaravelLocalization::getCurrentLocale();
@endphp
<!-- Site header -->
<header
    class="fixed top-0 left-0 right-0 w-full z-40 bg-white/95 backdrop-blur-sm border-b border-slate-100 transition-all duration-300">
    <div class="max-w-6xl mx-auto px-5 sm:px-6">
        <div class="flex items-center justify-between h-16 md:h-18">

            <!-- Site branding -->
            <div class="shrink-0 me-4">
                <!-- Logo -->
                <a class="flex items-center gap-2 transition duration-150 ease-in-out" href="{{ route('home') }}"
                    aria-label="{{ config('app.name') }}">
                    <img src="{{ asset('images/logo.svg') }}" class="w-9 h-9" alt="{{ config('app.name') }} Logo" />
                    <span class="font-semibold text-lg text-slate-900 hidden sm:block">{{ config('app.name') }}</span>
                </a>
            </div>

            <!-- Desktop navigation -->
            <nav class="hidden md:flex md:grow">

                <!-- Desktop menu links -->
                <ul class="flex grow justify-start flex-wrap items-center gap-1">
                    @foreach ($navigation as $navItem)
                        <li>
                            <a class="text-sm font-medium text-slate-600 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out"
                                href="{{ $navItem['link'] }}">{{ $navItem['label'] }}</a>
                        </li>
                    @endforeach
                </ul>

                <!-- Desktop sign in links -->
                <ul class="flex grow justify-end flex-wrap items-center gap-3">

                    @auth
                        @if (! $isChatter)
                            <li>
                                <a class="text-sm font-medium text-slate-600 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out"
                                    href="{{ route('dashboard') }}">{{ __('landing.nav.dashboard') }}</a>
                            </li>
                        @elseif ($chatterAgent)
                            <li>
                                <a class="text-sm font-medium text-slate-600 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out"
                                    href="{{ $chatterAgent->chat_url }}">{{ $chatterAgent->name }}</a>
                            </li>
                        @endif
                        <li>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out cursor-pointer">
                                    {{ __('landing.nav.logout') }}
                                </button>
                            </form>
                        </li>
                    @else
                        <li>
                            <a class="text-sm font-medium text-slate-600 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out"
                                href="{{ route('login') }}">{{ __('landing.nav.login') }}</a>
                        </li>
                        <li>
                            <a class="text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 rounded-lg flex items-center gap-1.5 transition duration-150 ease-in-out shadow-sm"
                                href="{{ route('home') }}#pricing">
                                {{ __('landing.nav.get_started') }}
                                <svg class="w-4 h-4 rtl:-scale-x-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </a>
                        </li>
                    @endif

                    <!-- Divider before the language switcher -->
                    <li aria-hidden="true" class="h-5 w-px bg-slate-200"></li>

                    <!-- Language switcher (flag only) -->
                    @php $cur = LaravelLocalization::getSupportedLocales()[$currentLocale] ?? ['flag' => 'gb', 'native' => 'English']; @endphp
                    <li>
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open" @keydown.escape.window="open = false"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1.5 hover:bg-slate-50 transition cursor-pointer"
                                :aria-expanded="open" aria-haspopup="listbox" aria-label="{{ __('landing.nav.language') }}: {{ $cur['native'] }}">
                                <img src="{{ asset('images/flags/'.$cur['flag'].'.svg') }}" alt="{{ $cur['native'] }}" class="h-4 w-5 shrink-0 rounded-[2px] object-cover" />
                                <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.away="open = false"
                                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                class="absolute end-0 mt-2 max-h-80 w-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg z-50" role="listbox">
                                @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                                    <a rel="alternate" hreflang="{{ $localeCode }}" href="{{ LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}"
                                        @if ($currentLocale === $localeCode) aria-current="true" @endif
                                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition {{ $currentLocale === $localeCode ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:bg-slate-50' }}">
                                        <img src="{{ asset('images/flags/'.$properties['flag'].'.svg') }}" alt="" class="h-4 w-5 shrink-0 rounded-[2px] object-cover" />
                                        <span class="flex-1">{{ $properties['native'] }}</span>
                                        @if ($currentLocale === $localeCode)
                                            <svg class="h-4 w-4 shrink-0 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </li>
                </ul>

                </nav>

                <!-- Mobile menu -->
                <div class="flex md:hidden" x-data="{ expanded: false }">

                    <!-- Hamburger button -->
                    <button
                        class="group inline-flex w-10 h-10 text-slate-700 hover:text-slate-900 text-center items-center justify-center transition"
                        aria-controls="mobile-nav" :aria-expanded="expanded" @click.stop="expanded = !expanded">
                        <span class="sr-only">{{ __('landing.nav.menu') }}</span>
                        <svg class="w-5 h-5 fill-current pointer-events-none" viewBox="0 0 16 16"
                            xmlns="http://www.w3.org/2000/svg">
                            <rect
                                class="origin-center transition-all duration-300 ease-[cubic-bezier(.5,.85,.25,1.1)] -translate-y-[5px] group-aria-expanded:rotate-[315deg] group-aria-expanded:translate-y-0"
                                y="7" width="16" height="2" rx="1"></rect>
                            <rect
                                class="origin-center group-aria-expanded:rotate-45 transition-all duration-300 ease-[cubic-bezier(.5,.85,.25,1.8)]"
                                y="7" width="16" height="2" rx="1"></rect>
                            <rect
                                class="origin-center transition-all duration-300 ease-[cubic-bezier(.5,.85,.25,1.1)] translate-y-[5px] group-aria-expanded:rotate-[135deg] group-aria-expanded:translate-y-0"
                                y="7" width="16" height="2" rx="1"></rect>
                        </svg>
                    </button>

                    <!-- Mobile navigation -->
                    <nav id="mobile-nav"
                        class="absolute top-full left-0 w-full bg-white border-b border-slate-200 shadow-lg"
                        @click.away="expanded = false" @keydown.escape.window="expanded = false" x-show="expanded"
                        x-transition:enter="transition ease-out duration-200 transform"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-out duration-200" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0" x-cloak>
                        <ul class="px-5 py-4 space-y-1">
                            @foreach ($navigation as $navItem)
                                <li>
                                    <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition"
                                        href="{{ $navItem['link'] }}" @click="expanded = false">{{ $navItem['label'] }}</a>
                                </li>
                            @endforeach
                            <li class="pt-3 border-t border-slate-200 mt-3">
                                @auth
                                    @if (! $isChatter)
                                        <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition"
                                            href="{{ route('dashboard') }}" @click="expanded = false">{{ __('landing.nav.dashboard') }}</a>
                                    @elseif ($chatterAgent)
                                        <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition"
                                            href="{{ $chatterAgent->chat_url }}" @click="expanded = false">{{ $chatterAgent->name }}</a>
                                    @endif
                                    <form method="post" action="{{ route('logout') }}" class="mt-1">
                                        @csrf
                                        <button type="submit"
                                            class="flex w-full font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition cursor-pointer">
                                            {{ __('landing.nav.logout') }}
                                        </button>
                                    </form>
                                @else
                                    <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition"
                                        href="{{ route('login') }}" @click="expanded = false">{{ __('landing.nav.login') }}</a>
                                @endauth
                            </li>
                            @guest
                                <li class="pt-2">
                                    <a class="flex font-semibold text-white bg-indigo-600 hover:bg-indigo-700 py-3 px-4 rounded-lg justify-center transition"
                                        href="{{ route('home') }}#pricing" @click="expanded = false">{{ __('landing.nav.get_started') }}</a>
                                </li>
                            @endguest

                            <!-- Language switcher (mobile) -->
                            <li class="pt-3 border-t border-slate-200 mt-3">
                                <p class="px-3 pb-1.5 text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('landing.nav.language') }}</p>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                                        <a rel="alternate" hreflang="{{ $localeCode }}"
                                            href="{{ LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}"
                                            @if ($currentLocale === $localeCode) aria-current="true" @endif
                                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition {{ $currentLocale === $localeCode ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">
                                            <img src="{{ asset('images/flags/'.$properties['flag'].'.svg') }}" alt="" class="h-4 w-5 shrink-0 rounded-[2px] object-cover" />
                                            <span class="truncate">{{ $properties['native'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </li>
                        </ul>
                    </nav>

                </div>

            </div>
        </div>
    </header>
