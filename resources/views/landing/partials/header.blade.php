<!-- Site header -->
<header class="absolute w-full z-30 dark">
    <div class="max-w-6xl mx-auto px-5 sm:px-6">
        <div class="flex items-center justify-between h-16 md:h-20">

            <!-- Site branding -->
            <div class="shrink-0 mr-4">
                <!-- Logo -->
                <a class="block text-blue-600 transition duration-150 ease-in-out" href="{{ route('home') }}"
                   aria-label="{{ config('app.name') }}">
                    <img src="{{ asset('images/logo.png') }}" class="w-12" alt="{{ config('app.name') }}"/>

                </a>
            </div>

            <!-- Desktop navigation -->
            <nav class="hidden md:flex md:grow">

                <!-- Desktop menu links -->
                <ul class="flex grow justify-start flex-wrap items-center">
                    @foreach(landing()['navigation'] as $navItem)
                        <a class="font-medium text-slate-800 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-600 px-3 lg:px-5 py-2 flex items-center transition duration-150 ease-in-out"
                           href="{{ $navItem['link'] }}">{{ $navItem['label'] }}</a>
                    @endforeach
                </ul>

                <!-- Desktop sign in links -->
                <ul class="flex grow justify-end flex-wrap items-center">
                    @auth
                        <li>
                            <a class="font-medium text-slate-800 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-600 px-3 lg:px-5 py-2 flex items-center transition duration-150 ease-in-out"
                               href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                    @else
                        @if (host_config('user')['demo'] === true)
                            <li>
                                <a class="font-medium text-blue-600 dark:text-slate-300 dark:hover:text-white px-3 lg:px-5 py-2 flex items-center transition duration-150 ease-in-out group"
                                   href="{{ route('demo') }}">Request Demo <span
                                        class="tracking-normal text-blue-600 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1"></span></a>
                            </li>
                        @else
                            <li class="ml-2">
                                <a class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg flex items-center transition duration-150 ease-in-out"
                                   href="{{ route('register') }}">Register</a>
                            </li>
                            <li class="ml-2">
                                <a class="bg-white hover:bg-gray-50 text-slate-800 font-medium px-4 py-2 rounded-lg border border-slate-200 flex items-center transition duration-150 ease-in-out"
                                   href="{{ route('login') }}">Login</a>
                            </li>
                        @endif

                    @endif
                </ul>

            </nav>

            <!-- Mobile menu -->
            <div class="flex md:hidden" x-data="{ expanded: false }">

                <!-- Hamburger button -->
                <button
                    class="group inline-flex w-8 h-8 text-slate-300 hover:text-white text-center items-center justify-center transition"
                    aria-controls="mobile-nav" :aria-expanded="expanded" @click.stop="expanded = !expanded">
                    <span class="sr-only">Menu</span>
                    <svg class="w-4 h-4 fill-current pointer-events-none" viewBox="0 0 16 16"
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
                    class="absolute top-full h-screen pb-16 z-20 left-0 w-full overflow-scroll no-scrollbar bg-white"
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
                    <ul class="px-5 py-2">
                        @foreach(landing()['navigation'] as $navItem)
                            <li>
                                <a class="flex font-medium text-slate-800 hover:text-blue-600 py-2" href="{{ $navItem['link'] }}">{{ $navItem['label'] }}</a>
                            </li>
                        @endforeach
                        @auth
                            <li>
                                <a class="flex font-medium text-slate-800 hover:text-blue-600 py-2" href="{{ route('dashboard') }}">Dashboard</a>
                            </li>
                        @else
                            <li>
                                <a class="flex font-medium w-full text-slate-800 hover:text-blue-600 py-2"
                                   href="{{ route('login') }}">Sign in</a>
                            </li>
                            <li>
                                <a class="flex font-medium text-blue-600 py-2 group" href="{{ route('register') }}">Register
                                    <span
                                        class="tracking-normal text-blue-600 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1">→</span></a>
                            </li>
                            @if (host_config('user')['demo'] === true)
                                <li>
                                    <a class="flex font-medium text-blue-600 py-2 group" href="{{ route('demo') }}">Request Demo
                                        <span
                                            class="tracking-normal text-blue-600 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1">→</span></a>
                                </li>
                            @endif
                        @endauth
                    </ul>
                </nav>

            </div>

        </div>
    </div>
</header>
