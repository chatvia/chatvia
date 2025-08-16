<!-- Site header -->
<header class="absolute top-2 md:top-6 w-full z-30">
    <div class="px-4 sm:px-6">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between h-14 border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg px-3">

                <!-- Site branding -->
                <div class="shrink-0 mr-4">
                    <!-- Logo -->
                    <a class="flex items-center justify-center w-8 h-8"
                       href="{{ route('home') }}">
                        <img src="{{ asset('images/logo.svg') }}" width="24" height="24" alt="Logo">
                    </a>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden" x-data="{ mobileMenuOpen: false }">
                    <button
                            class="text-zinc-500 hover:text-zinc-900 p-2"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            :aria-expanded="mobileMenuOpen"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <!-- Mobile menu dropdown -->
                    <div
                            x-show="mobileMenuOpen"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute top-full left-3 right-3 mt-2 bg-white rounded-lg shadow-lg border border-zinc-200 py-2 z-50"
                            @click.away="mobileMenuOpen = false"
                    >
                        <a href="#features" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                           @click="mobileMenuOpen = false; document.getElementById('features').scrollIntoView({behavior: 'smooth', block: 'start'})">Features</a>
                        <a href="#pricing" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                           @click="mobileMenuOpen = false; document.getElementById('pricing').scrollIntoView({behavior: 'smooth', block: 'start'})">Pricing</a>
                        <a href="#faqs" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                           @click="mobileMenuOpen = false; document.getElementById('faqs').scrollIntoView({behavior: 'smooth', block: 'start'})">FAQs</a>
                        <a href="{{ route('changelog') }}" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                           @click="mobileMenuOpen = false">Changelog</a>
{{--                        <div class="border-t border-zinc-200 my-2"></div>--}}
{{--                        <div class="px-4 py-2">--}}
{{--                            <div class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-2">Use Cases</div>--}}
{{--                            <a href="{{ route('use-cases', 'law') }}" class="block text-sm text-zinc-700 hover:bg-zinc-50 px-2 py-1 rounded"--}}
{{--                               @click="mobileMenuOpen = false">Legal</a>--}}
{{--                        </div>--}}
                        <div class="border-t border-zinc-200 my-2"></div>
                        @auth
                            <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                               @click="mobileMenuOpen = false">Dashboard</a>

                        @else
                            <a href="{{ route('register') }}"
                               class="block px-4 py-2 text-sm text-white bg-zinc-900 hover:bg-zinc-800 mx-4 rounded-md text-center"
                               @click="mobileMenuOpen = false">Register</a>
                            <div class="my-2"></div>
                            <a href="{{ route('login') }}"
                               class="block px-4 py-2 text-sm text-white bg-zinc-900 hover:bg-zinc-800 mx-4 rounded-md text-center"
                               @click="mobileMenuOpen = false">Login</a>
                        @endauth

                    </div>
                </div>

                <!-- Desktop navigation -->
                <nav class="hidden md:flex grow">

                    <!-- Desktop menu items -->
                    <ul class="flex grow justify-start flex-wrap items-center ml-8">
                        <li>
                            <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                               href="#pricing" onclick="document.getElementById('pricing').scrollIntoView({behavior: 'smooth', block: 'start'})">Pricing</a>
                        </li>
                        <li>
                            <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                               href="#features" onclick="document.getElementById('features').scrollIntoView({behavior: 'smooth', block: 'start'})">Features</a>
                        </li>
{{--                        <li class="relative" x-data="{ useCasesOpen: false }">--}}
{{--                            <button class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"--}}
{{--                                    @click="useCasesOpen = !useCasesOpen"--}}
{{--                                    @click.away="useCasesOpen = false">--}}
{{--                                Use Cases--}}
{{--                                <svg class="w-4 h-4 ml-1 transition-transform" :class="useCasesOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>--}}
{{--                                </svg>--}}
{{--                            </button>--}}
{{--                            <div x-show="useCasesOpen"--}}
{{--                                 x-transition:enter="transition ease-out duration-200"--}}
{{--                                 x-transition:enter-start="opacity-0 scale-95"--}}
{{--                                 x-transition:enter-end="opacity-100 scale-100"--}}
{{--                                 x-transition:leave="transition ease-in duration-75"--}}
{{--                                 x-transition:leave-start="opacity-100 scale-100"--}}
{{--                                 x-transition:leave-end="opacity-0 scale-95"--}}
{{--                                 class="absolute top-full left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-zinc-200 py-2 z-50">--}}
{{--                                <a href="{{ route('use-cases', 'law') }}" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50">Legal</a>--}}
{{--                            </div>--}}
{{--                        </li>--}}
                        <li>
                            <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                               href="#faqs" onclick="document.getElementById('faqs').scrollIntoView({behavior: 'smooth', block: 'start'})">FAQs</a>
                        </li>
                        <li>
                            <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                               href="{{ route('changelog') }}">Changelog</a>
                        </li>
                    </ul>

                    <!-- Desktop sign in links -->
                    <ul class="flex justify-end flex-wrap items-center">
                        @auth
                            <li>
                                <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                   href="{{ route('dashboard') }}">Dashboard</a>
                            </li>

                            <li class="ml-1">
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                <button class="btn-sm cursor-pointer text-zinc-100 bg-zinc-900 hover:bg-zinc-800 w-full shadow-sm"
                                   >Logout</button>
                                </form>
                            </li>
                        @else
                            <li>
                                <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                   href="{{ route('login') }}">Log in</a>
                            </li>
                            <li class="ml-1">
                                <a class="btn-sm text-zinc-100 bg-zinc-900 hover:bg-zinc-800 w-full shadow-sm"
                                   href="{{ route('register') }}">Get Started</a>
                            </li>

                        @endauth
                    </ul>

                </nav>

            </div>
        </div>
    </div>
</header>
