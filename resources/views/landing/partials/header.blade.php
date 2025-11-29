<!-- Site header -->
<header class="fixed w-full z-50 bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-5 sm:px-6">
        <div class="flex items-center justify-between h-16 md:h-18">

            <!-- Site branding -->
            <div class="shrink-0 mr-4">
                <!-- Logo -->
                <a class="flex items-center gap-2 transition duration-150 ease-in-out" href="{{ route('home') }}"
                   aria-label="{{ config('app.name') }}">
                    <img src="{{ asset('images/logo.png') }}" class="w-10 h-10" alt="{{ config('app.name') }} Logo"/>
                    <span class="font-semibold text-lg text-slate-800 hidden sm:block">{{ config('app.name') }}</span>
                </a>
            </div>

            <!-- Desktop navigation -->
            <nav class="hidden md:flex md:grow">

                <!-- Desktop menu links -->
                <ul class="flex grow justify-center flex-wrap items-center gap-1">
                    @foreach(landing()['navigation'] as $navItem)
                        <li>
                            <a class="text-sm font-medium text-slate-700 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out"
                               href="{{ $navItem['link'] }}">{{ $navItem['label'] }}</a>
                        </li>
                    @endforeach
                </ul>

                <!-- Desktop sign in links -->
                <ul class="flex grow justify-end flex-wrap items-center gap-3">
                    @auth
                        <li>
                            <a class="text-sm font-medium text-slate-700 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out"
                               href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                    @else
                        @if (host_config('user')['demo'] === true)
                            <li>
                                <a class="text-sm font-medium text-slate-700 hover:text-slate-900 px-4 py-2 flex items-center transition duration-150 ease-in-out"
                                   href="{{ route('demo') }}">Request Demo</a>
                            </li>
                        @endif
                        <li>
                            <a class="text-sm font-medium text-slate-700 hover:text-slate-900 border border-slate-300 hover:border-slate-400 px-5 py-2 rounded-full flex items-center transition duration-150 ease-in-out"
                               href="{{ route('login') }}">Log in</a>
                        </li>
                        <li>
                            <a class="text-sm font-semibold text-white bg-red-500 hover:bg-red-600 px-5 py-2 rounded-full flex items-center transition duration-150 ease-in-out"
                               href="{{ route('home') }}#pricing">Sign up free</a>
                        </li>
                    @endif
                </ul>

            </nav>

            <!-- Mobile menu -->
            <div class="flex md:hidden" x-data="{ expanded: false }">

                <!-- Hamburger button -->
                <button
                    class="group inline-flex w-10 h-10 text-slate-700 hover:text-slate-900 text-center items-center justify-center transition"
                    aria-controls="mobile-nav" :aria-expanded="expanded" @click.stop="expanded = !expanded">
                    <span class="sr-only">Menu</span>
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
                <nav
                    id="mobile-nav"
                    class="absolute top-full left-0 w-full bg-white border-b border-slate-200"
                    @click.away="expanded = false"
                    @keydown.escape.window="expanded = false"
                    x-show="expanded"
                    x-transition:enter="transition ease-out duration-200 transform"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-out duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    x-cloak
                >
                    <ul class="px-5 py-4 space-y-1">
                        @foreach(landing()['navigation'] as $navItem)
                            <li>
                                <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition" href="{{ $navItem['link'] }}">{{ $navItem['label'] }}</a>
                            </li>
                        @endforeach
                        <li class="pt-3 border-t border-slate-200 mt-3">
                            @auth
                                <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition" href="{{ route('dashboard') }}">Dashboard</a>
                            @else
                                <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition"
                                   href="{{ route('login') }}">Log in</a>
                            @endauth
                        </li>
                        @guest
                            @if (host_config('user')['demo'] === true)
                                <li>
                                    <a class="flex font-medium text-slate-700 hover:text-slate-900 py-2.5 px-3 rounded-lg transition" href="{{ route('demo') }}">Request Demo</a>
                                </li>
                            @endif
                            <li class="pt-2">
                                <a class="flex font-semibold text-white bg-red-500 hover:bg-red-600 py-3 px-4 rounded-full justify-center transition" href="{{ route('home') }}#pricing">Sign up free</a>
                            </li>
                        @endguest
                    </ul>
                </nav>

            </div>

        </div>
    </div>
</header>
