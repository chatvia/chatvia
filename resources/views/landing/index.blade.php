@extends('landing.layouts.app')

@section('title', $landing['title'])

@section('content')
<div class="flex flex-col min-h-screen overflow-hidden">

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
                        @foreach($landing['navigation'] as $navItem)
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
                            @if ($demo === true)
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
                            @foreach($landing['navigation'] as $navItem)
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
                            @if ($demo === true)
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

    <!-- Page content -->
    <main class="grow">

        <!-- Hero -->
        <section class="relative">

            <!-- Dark background -->
            <div
                class="absolute inset-0 bg-slate-900 pointer-events-none -z-10 [clip-path:polygon(0_0,_5760px_0,_5760px_calc(100%_-_352px),_0_100%)]"
                aria-hidden="true"></div>

            <div class="flex justify-center max-w-6xl mx-auto px-4 sm:px-6">
                <div class="pt-32 pb-20 md:pt-40 md:pb-44">

                    <!-- Hero content -->
                    <div
                        class="max-w-xl mx-auto md:max-w-none flex flex-col items-center justify-center text-center md:space-y-16 space-y-16">

                        <div class="text-center" data-aos="fade-right">
                            <p class="text-xl text-slate-400">
                                {{ $landing['hero']['top'] }}
                            </p>
                            <h1 class="mx-auto md:max-w-3xl text-5xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl font-extrabold font-rubik text-slate-100 mb-4 leading-tight text-center">
    {{ $landing['hero']['headline'] }}
</h1>

                            @foreach ($landing['hero']['subheadlines'] as $subheadline)
                            <p class="text-xl text-slate-400 mb-6">
                                {{ $subheadline }}
                            </p>
                            @endforeach

                            <div
                                class="max-w-xs mx-auto sm:max-w-none sm:flex sm:justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                                <div>
                                    <a class="btn text-white bg-blue-600 hover:bg-blue-700 w-full group"
                                       href="{{ route($landing['hero']['button']['route']) }}">
                                        {{ $landing['hero']['button']['label'] }}
                                    </a>
                                </div>

                            </div>
                            <p class="text-sm text-slate-500 mt-4">
                                {{ $landing['hero']['text_below_button'] }}
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </section>



        <div class="relative flex flex-col items-center justify-center mt-3" x-data="{ bubbles: {{ Js::from($landing['bubbles']) }}, visible: [] }"
             x-init="bubbles.forEach((b, i) => setTimeout(() => visible.push(i), b.delay))">
            <div class="relative w-full flex items-center justify-center">
                <img src="{{ asset('images/girl.png') }}" alt="Girl holding phone"
                     class="w-full max-w-3xl h-auto rounded-2xl z-10"/>
                <!-- Chat bubbles -->
                <template x-for="(bubble, i) in bubbles" :key="i">
                    <div x-show="visible.includes(i)" x-transition
                         :class="{
                            'absolute': true,
                            // Mobile positioning (closer to image, smaller)
                            'left-2 top-4 md:left-100 md:top-16': bubble.pos === 'top-left',
                            'right-2 top-12 md:right-100 md:top-16': bubble.pos === 'top-right',
                            'left-2 bottom-12 md:left-100 md:bottom-16': bubble.pos === 'bottom-left',
                            'right-2 bottom-4 md:right-100 md:bottom-16': bubble.pos === 'bottom-right',
                        }"
                         class="z-20">
                        <div :class="bubble.from === 'bot' ? 'bg-blue-600 text-white' : 'bg-white text-slate-800'"
                             class="rounded-xl px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-base max-w-32 md:max-w-72 shadow-lg"
                             x-text="bubble.text">
                        </div>
                    </div>
                </template>
            </div>
        </div>

        @if (isset($landing['llm_marquee']) && $landing['llm_marquee']['enabled'] && count($landing['llm_marquee']['llms']) > 0)
        <!-- LLM Marquee Section -->
        <section class="relative py-12 md:py-20 bg-slate-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center mb-12">
                    <h2 class="h3 font-playfair-display text-slate-800 mb-4">
                        {{ $landing['llm_marquee']['title'] }}
                    </h2>
                    <p class="text-lg text-slate-600">
                        {{ $landing['llm_marquee']['subtitle'] }}
                    </p>
                </div>

                <!-- Marquee Container -->
                <div class="relative overflow-hidden">
                    <!-- Gradient overlays for smooth fade effect -->
                    <div class="absolute left-0 top-0 w-20 h-full bg-gradient-to-r from-slate-50 to-transparent z-10"></div>
                    <div class="absolute right-0 top-0 w-20 h-full bg-gradient-to-l from-slate-50 to-transparent z-10"></div>

                    <!-- Marquee content -->
                    <div class="flex whitespace-nowrap" style="animation: marquee 20s linear infinite;">
                        @foreach($landing['llm_marquee']['llms'] as $llm)
                        <div class="flex items-center justify-center mx-8 min-w-[120px]">
                            <img src="{{ asset('images/llms/' . $llm['logo']) }}"
                                 alt="{{ $llm['name'] }}"
                                 class="h-12 w-auto object-contain opacity-60 hover:opacity-100 transition-opacity duration-300 filter grayscale hover:grayscale-0"/>
                        </div>
                        @endforeach
                        <!-- Duplicate for seamless loop -->
                        @foreach($landing['llm_marquee']['llms'] as $llm)
                        <div class="flex items-center justify-center mx-8 min-w-[120px]">
                            <img src="{{ asset('images/llms/' . $llm['logo']) }}"
                                 alt="{{ $llm['name'] }}"
                                 class="h-12 w-auto object-contain opacity-60 hover:opacity-100 transition-opacity duration-300 filter grayscale hover:grayscale-0"/>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        @endif

        <!-- Features Section Wrapper -->
        <div id="features">
        @if (isset($landing['features']) && count($landing['features']['short']) !== 0)
            <section>
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20">

                    <!-- Items -->
                    <div
                        class="relative max-w-sm mx-auto grid gap-16 md:grid-cols-2 lg:grid-cols-3 lg:gap-y-20 items-start md:max-w-2xl lg:max-w-none"
                        data-aos-id-blocks>

                        <!-- Lines decoration -->
                        <div class="absolute inset-0 -my-8 md:-my-12 pointer-events-none hidden md:flex"
                             aria-hidden="true">
                            <div
                                class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100"></div>
                            <div
                                class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100"></div>
                            <div
                                class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100"></div>
                            <div
                                class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100"></div>
                        </div>

                        @foreach ($landing['features']['short'] as $feature)
                        <div class="relative" data-aos="fade-up" data-aos-anchor="[data-aos-id-blocks]">
                            <div class="mb-5">
                                <img src="{{ asset('images/icons/'. $feature['icon']) }}.svg" alt="{{ $feature['title'] }}" class="w-15" />
                            </div>
                            <h3 class="h4 font-playfair-display mb-2">
                                {{ $feature['title'] }}
                            </h3>
                            <p class="text-lg text-slate-500">
                                {{ $feature['description'] }}
                            </p>
                        </div>
                        @endforeach
                    </div>

                </div>
            </div>
        </section>
        @endif


        @if (isset($landing['features']['slide']['tabs']) && count($landing['features']['slide']['tabs']) !== 0)
            <section class="relative">
            <div class="absolute inset-0 bg-slate-100 pointer-events-none mb-96 md:mb-[40rem]" aria-hidden="true"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20">

                    @if(!empty($landing['features']['slide']['title']) || !empty($landing['features']['slide']['subheadline']))
                    <!-- Section header -->
                    <div class="max-w-4xl mx-auto text-center pb-16">
                        @if(!empty($landing['features']['slide']['title']))
                        <h2 class="h2 font-playfair-display text-slate-800 mb-4">
                            {{ $landing['features']['slide']['title'] }}
                        </h2>
                        @endif
                        @if(!empty($landing['features']['slide']['subheadline']))
                            <p class="text-xl text-slate-500">
                                {{ $landing['features']['slide']['subheadline'] }}
                            </p>
                        @endif
                    </div>
                    @endif

                    <!-- Section content -->
                    <div class="max-w-6xl mx-auto"
                         x-data="{
                             tab: '1',
                             autoSlide: true,
                             totalTabs: {{ count($landing['features']['slide']['tabs']) }},
                             init() {
                                 this.startAutoSlide();
                             },
                             startAutoSlide() {
                                 if (this.autoSlide) {
                                     setInterval(() => {
                                         if (this.autoSlide) {
                                             let nextTab = parseInt(this.tab) + 1;
                                             if (nextTab > this.totalTabs) {
                                                 nextTab = 1;
                                             }
                                             this.tab = nextTab.toString();
                                         }
                                     }, 4000);
                                 }
                             },
                             selectTab(tabNumber) {
                                 this.tab = tabNumber;
                                 this.autoSlide = false;
                                 setTimeout(() => {
                                     this.autoSlide = true;
                                 }, 8000);
                             }
                         }">

                        <!-- Single column layout -->
                        <div class="text-center">

                            <!-- Tab content - titles, descriptions and images combined -->
                            @foreach ($landing['features']['slide']['tabs'] as $index => $tab)
                            <div x-show="tab === '{{ $index + 1 }}'">
                                <!-- Title and description for each tab -->
                                <div class="mb-12">
                                    <h3 class="text-3xl md:text-4xl lg:text-5xl font-playfair-display text-slate-800 mb-6">
                                        {{ $tab['title'] }}
                                    </h3>
                                    <p class="text-lg md:text-xl text-slate-600 leading-relaxed max-w-3xl mx-auto mb-8">
                                        {{ $tab['description'] ?? 'Discover how this powerful feature can transform your workflow and enhance your productivity with our innovative solution.' }}
                                    </p>
                                    @if(isset($tab['features']) && is_array($tab['features']))
                                    <div class="flex flex-wrap justify-center gap-6 max-w-2xl mx-auto">
                                        @foreach($tab['features'] as $feature)
                                        <div class="flex items-center space-x-2 text-slate-600">
                                            <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span>{{ $feature }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>

                                <!-- Image for each tab -->
                                <div class="relative mb-8">
                                    <div class="relative h-96 md:h-[32rem] lg:h-[40rem] max-w-5xl mx-auto">
                                        <img class="w-full h-full rounded-2xl shadow-2xl"
                                             src="{{ asset($tab['img']) }}"
                                             alt="{{ $tab['title'] }}"
                                             loading="lazy"/>
                                        <!-- Optional overlay for better text contrast -->
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent rounded-2xl"></div>
                                    </div>
                                </div>
                            </div>
                            @endforeach

                            <!-- Dot navigation -->
                            <div class="flex space-x-3 justify-center">
                                @foreach ($landing['features']['slide']['tabs'] as $index => $tab)
                                <button
                                    class="w-3 h-3 rounded-full transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    :class="tab === '{{ $index + 1 }}' ? 'bg-blue-600 scale-125' : 'bg-slate-300 hover:bg-slate-400'"
                                    @click="selectTab('{{ $index + 1 }}')"
                                    aria-label="Switch to tab {{ $index + 1 }}"
                                ></button>
                                @endforeach
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </section>
         @endif

        @if (count($landing['features']['slide2']['slides']) !== 0)
        <section>
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20 border-t border-slate-200">

                    <!-- Section header -->
                    <div class="max-w-3xl mx-auto text-center pb-12 md:pb-20">
                        <h2 class="h2 font-playfair-display text-slate-800">{{ $landing['features']['slide2']['title'] }}</h2>
                    </div>

                    <!-- Section content -->
                    <div
                        class="max-w-xl mx-auto md:max-w-none flex flex-col md:flex-row md:items-start md:space-x-8 lg:space-x-16 xl:space-x-18 space-y-8 space-y-reverse md:space-y-0"
                        x-data="{ tab: '1' }">

                        <!-- Tabs items (images) -->
                        <div class="md:rtl md:w-5/12 lg:w-1/2 order-1 md:order-none" data-aos="fade-down">
                            <div class="relative flex flex-col">
                                @foreach($landing['features']['slide2']['slides'] as $index => $slide)
                                <div
                                    class="w-full"
                                    x-show="tab === '{{ $index + 1 }}'"
                                    x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                    x-transition:enter-start="opacity-0 -translate-y-16"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 translate-y-16"
                                >
                                    <img class="md:max-w-none mx-auto rounded-sm" src="{{ $slide['image'] ?? './images/features-home-02.png' }}"
                                         width="540" height="620" alt="{{ $slide['title'] ?? 'Feature slide ' . ($index + 1) }}"/>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="md:w-7/12 lg:w-1/2" data-aos="fade-up">

                            <!-- Tabs buttons -->
                            <div class="mb-8 md:mb-0">
                                @foreach($landing['features']['slide2']['slides'] as $index => $slide)
                                <button
                                    :class="tab !== '{{ $index + 1 }}' ? 'border-transparent opacity-50 hover:opacity-75' : 'border-2 border-blue-500 opacity-100'"
                                    class="flex items-start text-left bg-white border-2 px-5 py-3 rounded-sm shadow-md transition duration-300 ease-in-out {{ $loop->last ? '' : 'mb-3' }}"
                                    @click="tab = '{{ $index + 1 }}'"
                                >
                                    @if(isset($slide['icon']))
                                        {!! $slide['icon'] !!}
                                    @else
                                    <svg class="w-4 h-4 fill-current text-blue-600 shrink-0 mt-1 mr-4"
                                         viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M9.4 6.6c.8.8.8 2 0 2.8-.8.8-2 .8-2.8 0-.8-.8-5-7.8-5-7.8s7 4.2 7.8 5Z"/>
                                        <path
                                            d="M8 16c-4.4 0-8-3.6-8-8 0-.6.4-1 1-1s1 .4 1 1c0 3.3 2.7 6 6 6s6-2.7 6-6-2.7-6-6-6c-.6 0-1-.4-1-1s.4-1 1-1c4.4 0 8 3.6 8 8s-3.6 8-8 8Z"/>
                                    </svg>
                                    @endif
                                    <div>
                                        <div class="text-slate-800 font-medium mb-1">{{ $slide['title'] ?? 'Feature Title' }}</div>
                                        <div class="text-slate-500">{{ $slide['description'] ?? 'Feature description goes here.' }}</div>
                                    </div>
                                </button>
                                @endforeach
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </section>
        @endif

        @if (isset($landing['features']['steps']['steps']) && count($landing['features']['steps']['steps']) !== 0)
        <section class="relative">
            <!-- Dark background -->
            <div
                class="absolute inset-0 bg-slate-900 pointer-events-none -z-10 [clip-path:polygon(0_0,_5760px_0,_5760px_calc(100%_-_352px),_0_100%)] h-96 md:h-auto md:mb-64"
                aria-hidden="true"></div>

            <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20">

                    <!-- Section header -->
                    <div class="max-w-3xl mx-auto text-center pb-12 md:pb-20">
                        <h2 class="h2 font-playfair-display text-slate-100">
                            {{ $landing['features']['steps']['title'] }}
                        </h2>
                    </div>

                    <!-- Section content -->
                    <div
                        class="max-w-sm mx-auto md:max-w-none grid gap-12 md:grid-cols-3 md:gap-x-10 md:gap-y-10 items-start">

                        <!-- 1st article -->
                        <article data-aos="fade-up">
                            <div class="relative block group mt-8 mb-4" href="#0">
                                <div
                                    class="absolute inset-0 pointer-events-none border-2 border-slate-500 opacity-20 translate-x-4 -translate-y-4 group-hover:translate-x-0 group-hover:translate-y-0 transition duration-300 ease-out -z-10"
                                    aria-hidden="true"></div>
                                <div class="overflow-hidden">
                                    <img
                                        class="w-full aspect-square object-cover group-hover:scale-105 transition duration-700 ease-out"
                                        src="{{ asset('images/features-home-3-01.png') }}" width="340" height="340" alt="News 01"/>
                                </div>
                                <div
                                    class="w-16 h-16 absolute bg-linear-to-b from-blue-500 to-blue-600 rounded-full -top-8 left-8">
                                    <svg class="w-16 h-16 fill-current" viewBox="0 0 64 64"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path class="text-white"
                                              d="m30.152 39.848.672-.408C32.24 33.632 33.92 32 35.96 26.768a10.714 10.714 0 0 0-.432-2.28c-.288-.096-.888-.336-1.44-.336-1.776.48-3.48 1.632-5.208 2.088-1.272.336-.576 2.352.36 3.312.672-.384 1.872-1.008 2.28-.96-1.248 2.112-3 6.12-3 7.704 0 .528-.48.816-.48 1.104 0 .288.12.6.144.936.384-.24.48-.12.48.264.264.192.672.504 1.032.816.144-.744.384-1.56.888-1.464l-.6 1.704c.072.072.12.144.168.192Z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="h4 font-playfair-display mb-2">
                                <div class="text-slate-800 hover:underline hover:decoration-blue-100" href="#0">
                                    {{ $landing['features']['steps']['steps'][0]['title'] }}
                                </div>
                            </h3>

                        </article>

                        <!-- 2nd article -->
                        <article data-aos="fade-up" data-aos-delay="100">
                            <div class="relative block group mt-8 mb-4" href="#0">
                                <div
                                    class="absolute inset-0 pointer-events-none border-2 border-slate-500 opacity-20 translate-x-4 -translate-y-4 group-hover:translate-x-0 group-hover:translate-y-0 transition duration-300 ease-out -z-10"
                                    aria-hidden="true"></div>
                                <div class="overflow-hidden">
                                    <img
                                        class="w-full aspect-square object-cover group-hover:scale-105 transition duration-700 ease-out"
                                        src="./images/features-home-3-02.png" width="342" height="342" alt="News 02"/>
                                </div>
                                <div
                                    class="w-16 h-16 absolute bg-linear-to-b from-blue-500 to-blue-600 rounded-full -top-8 left-8">
                                    <svg class="w-16 h-16 fill-current" viewBox="0 0 64 64"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path class="text-white"
                                              d="M25.508 39.044c.312 0 .672-.024 1.008-.048-.096-.432.384-.528.816-.096 1.488-.216 3.12-.624 4.416-.888l.6-.624c1.104 0 .456.624 1.44.432.168-1.008.552-.456 1.92-1.176l.072.168c-.264.192-.72.408-1.152.576v.216c1.584-.528 1.848-1.32 2.928-1.464 1.224-.168 2.184-.048 2.52-.792l-1.728-.096-.168-.24h1.008l-.096-.216c-1.032 0-1.44-.168-2.4-.576-2.016.192-5.328.912-7.632.768 1.296-1.512 3.72-2.592 5.712-3.648.024.36-.384.6-.744.816l.072.216 1.344-.744c.048-.72.552-1.344 1.272-1.44.6-.744.864-1.776.864-2.76 0-1.08-1.2-2.208-3.024-2.208l.096-.264c-1.968.192-5.4.36-6.792 1.176-.504.288-.456.696-.792.984.48.36.024.528.504 1.2.624.888.648 1.488 1.152 1.2 1.056-.576 2.4-1.176 3.384-1.176.48 0 .864.168 1.104.504 0 1.488-6.744 3.792-9.288 7.632.48.096.312.48.072.84.504.768.84 1.728 1.512 1.728Zm7.344-5.976.912-.48-.048-.264-.912.6.048.144Zm7.08 3.216-.096-.216-1.128.216c.144.168.72.048 1.224 0Z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="h4 font-playfair-display mb-2">
                                <div class="text-slate-800 hover:underline hover:decoration-blue-100" href="#0">
                                    {{ $landing['features']['steps']['steps'][1]['title'] }}
                                </div>
                            </h3>

                        </article>

                        <!-- 3rd article -->
                        <article data-aos="fade-up" data-aos-delay="200">
                            <div class="relative block group mt-8 mb-4">
                                <div
                                    class="absolute inset-0 pointer-events-none border-2 border-slate-500 opacity-20 translate-x-4 -translate-y-4 group-hover:translate-x-0 group-hover:translate-y-0 transition duration-300 ease-out -z-10"
                                    aria-hidden="true"></div>
                                <div class="overflow-hidden">
                                    <img
                                        class="w-full aspect-square object-cover group-hover:scale-105 transition duration-700 ease-out"
                                        src="{{ asset('images/features-home-3-03.png') }}" width="342" height="342" alt="News 03"/>
                                </div>
                                <div
                                    class="w-16 h-16 absolute bg-linear-to-b from-blue-500 to-blue-600 rounded-full -top-8 left-8">
                                    <svg class="w-16 h-16 fill-current" viewBox="0 0 64 64"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path class="text-white"
                                              d="M25.53 40.038c1.607-.216 3.071-.576 4.655-1.08.048-.36.288-.6.552-.624l.12.408a37.15 37.15 0 0 0 2.352-.912l.12.264c1.416-.72 2.64-1.2 3.984-2.376l.312.144c1.392-1.248 1.632-2.904 1.632-4.728-.624-.912-1.416-1.656-2.784-2.112.36-.72.768-1.752.984-2.784-.12-.648-.408-1.224-.864-1.776-.84-.072-2.088-.24-3.264-.24l-.192-.216a18.485 18.485 0 0 0-1.152-.048c-1.776 0-4.056 1.032-4.056 1.944 0 .624.048 1.248.192 1.824l.268-.172c.578-.368 1.127-.671 1.46-.38l-1.656.792c.048.144.12.336.192.48 1.296-.672 3.96-1.608 5.64-1.608-.456.984-2.376 2.496-3.672 2.832l-.24 1.056c.456 1.248.864 1.032 2.16 1.68l.12-.288c1.8.216 3.336 0 3.96.72-.312 1.464-3.984 2.712-5.4 3.12-.504.144-.84.144-.936-.096-1.416.744-3.36.696-5.064.696-.24.768-.336 1.608.048 2.544l.72-.312c.144.288-.432.696-.456.96-.024.24.12.312.264.288Zm7.703-2.352-.144-.264.744-.312.072.216-.672.36Zm-4.344 1.248-.048-.264c.216-.048.384-.096.552-.12a.588.588 0 0 1 .48.12l-.984.264Z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="h4 font-playfair-display mb-2">
                                <div class="text-slate-800 hover:underline hover:decoration-blue-100" href="#0">
                                    {{ $landing['features']['steps']['steps'][2]['title'] }}
                                </div>
                            </h3>

                        </article>

                    </div>

                </div>
            </div>
        </section>
        @endif
        </div>
        <!-- End Features Section Wrapper -->

        <!-- Target -->
        @if(isset($landing['show-why-chatbot']) && $landing['show-why-chatbot'] === true)
        <section>
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20 border-t border-slate-200">

                    <!-- Section header -->
                    <div class="max-w-2xl mx-auto text-center pb-12 md:pb-20">
                        <h2 class="h2 font-rubik text-slate-800 mb-3">
                            Why you should have a chatbot for your business?
                        </h2>
                        <p class="text-xl text-slate-500">
                            Having a chatbot can help your business grow, manage customer inquiries, and improve
                            customer satisfaction. Here are some reasons why you should have a chatbot for your
                            business:
                        </p>
                    </div>

                    <!-- Section content -->
                    <div
                        class="max-w-xl mx-auto md:max-w-none flex flex-col md:flex-row md:items-center md:space-x-8 lg:space-x-16 xl:space-x-18 space-y-8 space-y-reverse md:space-y-0">

                        <!-- Content -->
                        <div class="md:w-7/12 lg:w-1/2 order-1 md:order-none" data-aos="fade-right">
                            <ul class="space-y-6">
                                <li>
                                    <div class="flex items-center mb-4">
                                        <svg class="h-4 w-4 shrink-0 fill-current text-rose-400 mr-3">
                                            <path
                                                d="M15.722 4.008C14.408 1.214 10.954-.635 7.318.203 5.6.596 4.072 1.561 2.919 2.757A10.57 10.57 0 0 0 .484 6.93C.03 8.458-.173 10.035.18 11.764c.191.862.518 1.683 1.146 2.479a4.876 4.876 0 0 0 2.256 1.522c1.635.469 3.156.192 4.41-.439 1.242-.615 2.298-1.769 2.494-3.094.094-.656-.537-.657-.69-.18-.781 2.126-3.715 2.534-5.265 1.579-1.568-.922-1.185-3.068-.294-4.801.89-1.729 2.454-3.02 3.92-3.338.376-.098.714-.121 1.026-.098.324.018.658.074.98.188.65.2 1.23.591 1.618 1 .27.3.575.386 1.002.461.436.061.95.117 1.499.045.535-.073 1.06-.287 1.41-.807.345-.504.462-1.348.03-2.273"/>
                                        </svg>
                                        <div class="h2 font-playfair-display text-slate-800">56M+</div>
                                    </div>
                                    <div class="text-slate-500 text-lg">
                                        As of 2025, Bank of America’s “Erica” chatbot handles 56 million engagements
                                        monthly and resolves 98% of queries within 44 seconds
                                    </div>
                                </li>
                                <li>
                                    <div class="flex items-center mb-4">
                                        <svg class="h-4 w-4 shrink-0 fill-current text-yellow-400 mr-3">
                                            <path
                                                d="M15.722 4.008C14.408 1.214 10.954-.635 7.318.203 5.6.596 4.072 1.561 2.919 2.757A10.57 10.57 0 0 0 .484 6.93C.03 8.458-.173 10.035.18 11.764c.191.862.518 1.683 1.146 2.479a4.876 4.876 0 0 0 2.256 1.522c1.635.469 3.156.192 4.41-.439 1.242-.615 2.298-1.769 2.494-3.094.094-.656-.537-.657-.69-.18-.781 2.126-3.715 2.534-5.265 1.579-1.568-.922-1.185-3.068-.294-4.801.89-1.729 2.454-3.02 3.92-3.338.376-.098.714-.121 1.026-.098.324.018.658.074.98.188.65.2 1.23.591 1.618 1 .27.3.575.386 1.002.461.436.061.95.117 1.499.045.535-.073 1.06-.287 1.41-.807.345-.504.462-1.348.03-2.273"/>
                                        </svg>
                                        <div class="h2 font-playfair-display text-slate-800">80%</div>
                                    </div>
                                    <div class="text-slate-500 text-lg">
                                        AI now handles up to 80% of customer support inquiries autonomously at
                                        ServiceNow, cutting complex case resolution time by 52%

                                    </div>
                                </li>
                                <li>
                                    <div class="flex items-center mb-4">
                                        <svg class="h-4 w-4 shrink-0 fill-current text-blue-500 mr-3">
                                            <path
                                                d="M15.722 4.008C14.408 1.214 10.954-.635 7.318.203 5.6.596 4.072 1.561 2.919 2.757A10.57 10.57 0 0 0 .484 6.93C.03 8.458-.173 10.035.18 11.764c.191.862.518 1.683 1.146 2.479a4.876 4.876 0 0 0 2.256 1.522c1.635.469 3.156.192 4.41-.439 1.242-.615 2.298-1.769 2.494-3.094.094-.656-.537-.657-.69-.18-.781 2.126-3.715 2.534-5.265 1.579-1.568-.922-1.185-3.068-.294-4.801.89-1.729 2.454-3.02 3.92-3.338.376-.098.714-.121 1.026-.098.324.018.658.074.98.188.65.2 1.23.591 1.618 1 .27.3.575.386 1.002.461.436.061.95.117 1.499.045.535-.073 1.06-.287 1.41-.807.345-.504.462-1.348.03-2.273"/>
                                        </svg>
                                        <div class="h2 font-playfair-display text-slate-800">62%</div>
                                    </div>
                                    <div class="text-slate-500 text-lg">
                                        62% of consumers would rather use a chatbot than wait for a human customer
                                        service agent,” signaling a strong preference for the immediacy that chatbots
                                        deliver
                                    </div>
                                </li>


                            </ul>
                        </div>

                        <!-- Image -->
                        <div class="md:w-5/12 lg:w-1/2" data-aos="fade-left">
                            <img class="mx-auto md:max-w-none" src="./images/target.png" width="540" height="540"
                                 alt="Target"/>
                        </div>

                    </div>

                </div>
            </div>
        </section>
        @endif


        <!-- Pricing -->
        @if ($pricing && isset($packages))
        <section class="relative" id="pricing">

            <!-- Dark background -->
            <div class="absolute inset-0 bg-slate-900 pointer-events-none -z-10 h-1/3 lg:h-2/3"
                 aria-hidden="true"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20">

                    <!-- Section header -->
                    <div class="max-w-3xl mx-auto text-center pb-12">
                        <h2 class="h2 font-playfair-display text-slate-100">
                            AI Agents don't need to cost a fortune
                        </h2>
                    </div>

                    <!-- Pricing tables -->
                    <div x-data="{ annual: true }">

                        <!-- Pricing toggle -->
                        <div class="flex justify-center items-center space-x-4 sm:space-x-7 mb-16">
                            <div class="text-sm text-slate-500 font-medium text-right min-w-[8rem]">Pay Monthly</div>
                            <div class="form-switch shrink-0">
                                <input type="checkbox" id="toggle" class="sr-only" x-model="annual"/>
                                <label class="bg-slate-700" for="toggle">
                                    <span aria-hidden="true"></span>
                                    <span class="sr-only">Pay annually</span>
                                </label>
                            </div>
                            <div class="text-sm text-slate-500 font-medium min-w-[8rem]">Pay Yearly <span
                                    class="text-emerald-500">(-{{ $billingConfig['ui']['yearly_savings_percentage'] ?? 50 }}%)</span></div>
                        </div>

                        <div class="max-w-sm mx-auto grid gap-8 lg:grid-cols-{{ min(count($packages), 4) }} lg:gap-6 items-start lg:max-w-none pt-4">

                            @foreach($packages as $packageKey => $package)
                            @php $numericIndex = $loop->index; @endphp

                            <div class="relative flex flex-col h-full px-6 py-5 bg-white shadow-lg" data-aos="fade-up"
                                 @if($numericIndex > 0) data-aos-delay="{{ $numericIndex * 100 }}" @endif>
                                @if($package['is_popular'])
                                <div class="absolute top-0 right-0 mr-6 -mt-4">
                                    <div
                                        class="inline-flex text-sm font-semibold py-1 px-3 text-emerald-700 bg-emerald-200 rounded-full">
                                        {{ $billingConfig['ui']['popular_badge_text'] ?? 'Most Popular' }}
                                    </div>
                                </div>
                                @endif
                                <div class="mb-4 pb-4 border-b border-slate-200">
                                    <div class="text-lg font-semibold text-slate-800 mb-1">{{ $package['name'] }}</div>
                                    <div class="inline-flex items-baseline mb-3">
                                        <span class="h3 font-medium text-slate-500">{{ $billingConfig['currencySymbol'] ?? '$' }}</span>
                                        <span x-text="annual ? '{{ number_format($package['yearly_price'] / 12 / 100, 0) }}' : '{{ number_format($package['monthly_price'] / 100, 0) }}'"
                                              class="h2 leading-7 font-playfair-display text-slate-800"></span>
                                        <span class="font-medium text-slate-400">/mo</span>
                                    </div>
                                    <div class="text-xs -mt-2 mb-2 h-5">
                                        <span x-show="annual" class="text-slate-400">
                                            Billed yearly at {{ $billingConfig['currencySymbol'] ?? '$' }}{{ number_format($package['yearly_price'] / 100, 0) }}
                                        </span>
                                    </div>
                                    <div class="text-slate-500">{{ $package['description'] }}</div>
                                </div>
                                <div class="font-medium mb-3">

                                    @if($numericIndex === 0)
                                        Features include:
                                    @else
                                        @php
                                            $previousPackage = $packages->values()[$numericIndex - 1];
                                        @endphp
                                        All features of {{ $previousPackage['name'] }} plus:
                                    @endif
                                </div>
                                <div class="grow mb-6">
                                    <!-- Monthly Features -->
                                    <ul class="text-slate-500 space-y-3 absolute" x-show="!annual">
                                        @foreach($package['features_monthly'] as $feature)
                                        <li class="flex items-center">
                                            <svg class="w-3 h-3 fill-current text-emerald-500 mr-3 shrink-0"
                                                 viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z"/>
                                            </svg>
                                            <span>{{ $feature }}</span>
                                        </li>
                                        @endforeach
                                    </ul>

                                    <!-- Yearly Features -->
                                    <ul class="text-slate-500 space-y-3 absolute" x-show="annual">
                                        @foreach($package['features_yearly'] as $feature)
                                        <li class="flex items-center">
                                            <svg class="w-3 h-3 fill-current text-emerald-500 mr-3 shrink-0"
                                                 viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z"/>
                                            </svg>
                                            <span>{{ $feature }}</span>
                                        </li>
                                        @endforeach
                                    </ul>

                                    <!-- Invisible spacer to maintain height based on the longest feature list -->
                                    <ul class="text-slate-500 space-y-3 invisible">
                                        @php
                                            $maxFeatures = max(count($package['features_monthly']), count($package['features_yearly']));
                                        @endphp
                                        @for($i = 0; $i < $maxFeatures; $i++)
                                        <li class="flex items-center">
                                            <svg class="w-3 h-3 fill-current text-emerald-500 mr-3 shrink-0"
                                                 viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z"/>
                                            </svg>
                                            <span>Placeholder feature</span>
                                        </li>
                                        @endfor
                                    </ul>
                                </div>
                                <div class="p-3 rounded-sm bg-slate-50">
                                    @if($package['id'] === 'free')
                                        <a class="btn-sm text-white bg-blue-600 hover:bg-blue-700 w-full group" href="{{ route('register') }}">
                                            Get Started Free <span
                                                class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1"></span>
                                        </a>
                                    @else
                                        <a class="btn-sm text-white bg-blue-600 hover:bg-blue-700 w-full group" href="{{ route('register') }}">
                                            Get started for free <span
                                                class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1"></span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                            @endforeach

                        </div>

                    </div>

                </div>
            </div>
        </section>
        @endif

        <!-- FAQs -->
        @if (isset($landing['faqs']) && count($landing['faqs']) > 0)
        <section id="faqs">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20 border-t border-slate-200">

                    <!-- Section header -->
                    <div class="max-w-3xl mx-auto text-center pb-12 md:pb-20">
                        <h2 class="h2 font-playfair-display text-slate-800 mb-4">
                            Frequently Asked Questions
                        </h2>
                        <p class="text-xl text-slate-500">
                            Everything you need to know about our AI chatbot platform.
                        </p>
                    </div>

                    <!-- FAQ Content -->
                    <div class="max-w-4xl mx-auto">
                        <div class="space-y-4">
                            @foreach($landing['faqs'] as $index => $faq)
                            <div class="bg-white rounded-lg border border-slate-200 shadow-sm"
                                 x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }"
                                 data-aos="fade-up"
                                 @if($index > 0) data-aos-delay="{{ $index * 100 }}" @endif>

                                <!-- Question -->
                                <button
                                    class="w-full text-left px-6 py-4 focus:outline-none rounded-lg transition-colors duration-200"
                                    :class="open ? 'bg-slate-50' : 'hover:bg-slate-50'"
                                    @click="open = !open"
                                    :aria-expanded="open">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-slate-800 pr-4">
                                            {{ $faq['question'] }}
                                        </h3>
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-slate-500 transition-transform duration-200 transform"
                                                 :class="open ? 'rotate-45' : 'rotate-0'"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                        </div>
                                    </div>
                                </button>

                                <!-- Answer -->
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 -translate-y-2">
                                    <div class="px-6 pb-4">
                                        <div class="text-slate-600 leading-relaxed">
                                            {!! nl2br(e($faq['answer'])) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        @if(isset($landing['has-widget']) && $landing['has-widget'] === true)
                        <div class="text-center mt-12">
                            <p class="text-lg text-slate-600 mb-4">
                                Still have questions?
                            </p>
                            <button onclick="window.chatbot('open')"
                               class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium transition-colors duration-200 cursor-pointer">
                                Get in touch with our support team
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </button>
                        </div>
                        @endif
                    </div>

                </div>
            </div>
        </section>
        @endif

        <!-- Cta -->
        <section class="bg-slate-100">
            <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20">

                    <div class="relative max-w-3xl mx-auto text-center">

                        <div class="absolute right-0 -mt-4 -mr-1 fill-slate-300 hidden lg:block" aria-hidden="true">
                            <svg class="fill-slate-300" width="56" height="43">
                                <path
                                    d="M4.532 30.45C15.785 23.25 24.457 12.204 29.766.199c.034-.074-.246-.247-.3-.186-4.227 5.033-9.298 9.282-14.372 13.162C10 17.07 4.919 20.61.21 24.639c-1.173 1.005 2.889 6.733 4.322 5.81M18.96 42.198c12.145-4.05 24.12-8.556 36.631-12.365.076-.024.025-.349-.055-.347-6.542.087-13.277.083-19.982.827-6.69.74-13.349 2.24-19.373 5.197-1.53.75 1.252 7.196 2.778 6.688"/>
                            </svg>
                        </div>

                        <div class="relative">
                            <h2 class="h2 font-playfair-display text-slate-800 mb-4">
                                {!! $landing['cta']['headline'] !!}
                            </h2>
                            <p class="text-xl text-slate-500 mb-8">
                                {!! $landing['cta']['description'] !!}
                            </p>
                            <div>
                                <a class="btn text-white bg-blue-600 hover:bg-blue-700 group" href="{{ route($landing['cta']['button']['route']) }}">
                                    {{ $landing['cta']['button']['label'] }} <span
                                        class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1"></span>
                                </a>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </section>

    </main>

    <!-- Site footer -->
    <footer>
        <div class="max-w-6xl mx-auto px-4 sm:px-6">

            <!-- Top area: Blocks -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between py-8 md:py-12">

                <!-- Logo and tagline -->
                <div class="mb-6 md:mb-0">
                    <div class="mb-2">
                        <!-- Logo -->
                        <a class="inline-flex text-blue-600 transition duration-150 ease-in-out"
                           href="{{ route('home') }}"
                           aria-label="{{ config('app.name') }}">
                            <img src="{{ asset('images/logo.png') }}" class="w-12" alt="{{ config('app.name') }}"/>
                        </a>
                    </div>

                </div>

                <!-- Legal links -->
                <div class="flex flex-wrap gap-6 md:gap-8">
                    <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                       href="{{ route('terms') }}">Terms of Service</a>
                    <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                       href="{{ route('privacy') }}">Privacy</a>
                    <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                       href="{{ route('refund') }}">Refund Policy</a>
                    <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                       href="{{ route('changelog') }}">Changelog</a>
                </div>

            </div>

            <!-- Bottom area -->
            <div class="md:flex md:items-center md:justify-between py-6 md:py-8 border-t border-slate-200">

                <ul class="flex space-x-6 mb-4 md:order-1 md:ml-4 md:mb-0">
                    <li>
                        <a class="text-blue-500 hover:text-blue-600 transition duration-150 ease-in-out" href="https://facebook.com/chatviaai"
                           aria-label="Facebook">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M20 10.025C20 4.491 15.52 0 10 0S0 4.491 0 10.025c0 4.852 3.44 8.892 8 9.825v-6.817H6v-3.008h2V7.52a3.508 3.508 0 0 1 3.5-3.509H14v3.008h-2c-.55 0-1 .45-1 1.002v2.005h3v3.008h-3V20c5.05-.501 9-4.772 9-9.975Z"/>
                            </svg>
                        </a>
                    </li>
                    <li>
                        <a class="text-blue-500 hover:text-blue-600 transition duration-150 ease-in-out" href="https://www.linkedin.com/company/chatvia-ai"
                           aria-label="LinkedIn">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M18.6 0H1.4C.6 0 0 .6 0 1.4v17.1C0 19.4.6 20 1.4 20h17.1c.8 0 1.4-.6 1.4-1.4V1.4C20 .6 19.4 0 18.6 0zM6 17H3V8h3v9zM4.5 6.3c-1 0-1.8-.8-1.8-1.8s.8-1.8 1.8-1.8 1.8.8 1.8 1.8-.8 1.8-1.8 1.8zM17 17h-3v-4.4c0-1.1 0-2.5-1.5-2.5s-1.7 1.2-1.7 2.4V17H8V8h2.8v1.2h.1c.4-.7 1.3-1.5 2.7-1.5 2.9 0 3.4 1.9 3.4 4.4V17z"/>
                            </svg>
                        </a>
                    </li>
                </ul>

                <!-- Copyrights note -->
                <div class="text-sm text-slate-500 mr-4">&copy; {{ config('app.name') }}.
                Made with ❤️ in Denmark

                </div>

            </div>

        </div>
    </footer>

</div>
@endsection
