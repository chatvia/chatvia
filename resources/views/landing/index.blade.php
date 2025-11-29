@extends('landing.layouts.app')

@section('title', landing('title') . ' - AI-Powered Chatbot Platform | RAG Technology')

@section('description', 'Build intelligent AI chatbots with ChatVia. Upload documents, create custom AI chatbots with RAG technology, and automate customer support. Start free today.')

@section('og_title', landing('title') . ' - AI-Powered Chatbot Platform')

@section('og_description', 'Transform your customer support with AI-powered chatbots. Upload documents, train agents, and provide instant 24/7 support with ChatVia.')

@push('preload')
    {{-- Preload first 3 feature images for better performance --}}
    @if(isset($landing['features']['slide']['tabs']))
        @foreach(array_slice($landing['features']['slide']['tabs'], 0, 3) as $tab)
            <link rel="preload" as="image" href="{{ asset($tab['img']) }}">
        @endforeach
    @endif
@endpush

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <!-- Page content -->
        <main class="grow">

            <!-- Hero -->
            <section class="relative bg-white">
                <div class="max-w-5xl mx-auto px-4 sm:px-6">
                    <div class="pt-28 pb-12 md:pt-36 md:pb-20">

                        <!-- Hero content -->
                        <div class="text-center" data-aos="fade-up">
                            <!-- Trust badges -->
                            <div class="flex flex-wrap items-center justify-center gap-3 mb-6">
                                <!-- GDPR -->
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 rounded-full text-sm text-slate-600">
                                    <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                    </svg>
                                    <span class="font-medium">GDPR</span>
                                </div>
                                <!-- Hosted in EU -->
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 rounded-full text-sm text-slate-600">
                                    <img src="{{ asset('images/eu-flag.svg') }}" alt="EU Flag" class="w-4 h-4 object-contain" />
                                    <span class="font-medium">Hosted in EU</span>
                                </div>
                                <!-- Made in Denmark -->
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 rounded-full text-sm text-slate-600">
                                    <img src="{{ asset('images/dk-flag.svg') }}" alt="Denmark Flag" class="w-4 h-4 object-contain" />
                                    <span class="font-medium">Made in Denmark</span>
                                </div>
                            </div>

                            <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-display text-slate-900 mb-6 leading-[1.1] tracking-tight">
                                Turn your website into<br class="hidden sm:block" />
                                a <span class="relative inline-block">
                                    <span class="relative z-10">high-converting</span>
                                    <span class="absolute bottom-1 left-0 right-0 h-3 md:h-4 bg-gradient-to-r from-blue-200 to-purple-200 -z-0 -skew-x-3"></span>
                                </span> AI chatbot
                            </h1>

                            <p class="text-lg md:text-xl text-slate-600 mb-8 max-w-2xl mx-auto">
                                ChatVia instantly helps your customers using AI-generated responses. Get 24/7 support and ultra-high satisfaction rates.
                            </p>

                            <!-- CTA Button -->
                            <div class="mb-12">
                                <a
                                    href="#pricing"
                                    class="inline-block px-10 py-4 text-lg font-semibold text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors duration-200"
                                >
                                    {{ $landing['hero']['button']['label'] ?? 'Start free trial' }}
                                </a>
                            </div>

                            <!-- Hero Image -->
                            <div class="relative max-w-5xl mx-auto" data-aos="fade-up" data-aos-delay="100">
                                <!-- Browser-style window -->
                                <div class="relative rounded-xl overflow-hidden shadow-2xl border border-slate-200 bg-slate-100">
                                    <!-- Window header with traffic lights -->
                                    <div class="flex items-center gap-2 px-4 py-3 bg-slate-50 border-b border-slate-200">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                            <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                            <div class="w-3 h-3 rounded-full bg-green-400"></div>
                                        </div>
                                    </div>
                                    <!-- Image with fade out effect -->
                                    <div class="relative">
                                        <img
                                            src="{{ asset('images/agent-stats.png') }}"
                                            alt="ChatVia AI Chatbot Dashboard - Analytics and conversation management"
                                            class="w-full h-auto"
                                        />
                                        <!-- Fade out gradient overlay -->
                                        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-white to-transparent"></div>
                                    </div>
                                </div>
                                <!-- Decorative gradient blur -->
                                <div class="absolute -inset-4 bg-gradient-to-r from-blue-500/20 via-purple-500/20 to-pink-500/20 blur-3xl -z-10 opacity-40"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- Core Features Section -->
            <section class="relative py-16 md:py-20 bg-white">
                <div class="max-w-5xl mx-auto px-4 sm:px-6">

                    <!-- Section header -->
                    <div class="text-center mb-12 md:mb-16" data-aos="fade-up">
                        <h2 class="h2 font-display text-slate-900 mb-4">
                            The complete platform for AI chatbots
                        </h2>
                        <p class="text-lg text-slate-500 max-w-2xl mx-auto">
                            ChatVia is designed for building AI chatbots that solve your customers' hardest problems while improving business outcomes.
                        </p>
                    </div>

                    <!-- Two Cards -->
                    <div class="grid md:grid-cols-2 gap-6">

                        <!-- Card 1: Chat Widget -->
                        <div class="bg-slate-50 rounded-xl p-6 lg:p-8" data-aos="fade-up">
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">Chat Widget</h3>
                            <p class="text-slate-600 mb-5">
                                Embed a powerful AI chat widget on your website. Customize the look and feel to match your brand and engage visitors 24/7.
                            </p>
                            <button type="button" onclick="window.chatbot('open')"
                               class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium text-sm">
                                See how it works in action
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Card 2: Knowledge Bases -->
                        <div class="bg-slate-50 rounded-xl p-6 lg:p-8" data-aos="fade-up" data-aos-delay="100">
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">Knowledge Bases</h3>
                            <p class="text-slate-600 mb-5">
                                Build custom knowledge bases like ChatGPT. Upload documents, websites, and files to train your AI on your specific content.
                            </p>
                            <a href="#pricing"
                               class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium text-sm">
                                See how it works in action
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>

                    </div>
                </div>
            </section>

            <!-- Features Bar -->
            <section class="bg-slate-900 py-4" data-aos="fade-up">
                <div class="max-w-7xl mx-auto px-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 md:gap-x-12">
                        <!-- Feature 1 -->
                        <div class="flex items-center gap-2 text-slate-300 text-sm">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                            </svg>
                            <span>AI-powered support</span>
                        </div>
                        <!-- Feature 2 -->
                        <div class="flex items-center gap-2 text-slate-300 text-sm">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Instant chatbot setup</span>
                        </div>
                        <!-- Feature 3 -->
                        <div class="flex items-center gap-2 text-slate-300 text-sm">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
                            </svg>
                            <span>Website widget</span>
                        </div>
                        <!-- Feature 4 -->
                        <div class="flex items-center gap-2 text-slate-300 text-sm">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
                            </svg>
                            <span>AI-generated replies</span>
                        </div>
                        <!-- Feature 5 -->
                        <div class="flex items-center gap-2 text-slate-300 text-sm">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
                            </svg>
                            <span>Advanced AI training</span>
                        </div>
                    </div>
                </div>
            </section>

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
                                                <img src="{{ asset('images/icons/'. $feature['icon']) }}.svg"
                                                     alt="{{ $feature['title'] }} icon" class="w-15"/>
                                            </div>
                                            <h2 class="h4 font-display mb-2">
                                                {{ $feature['title'] }}
                                            </h2>
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
                        <div class="absolute inset-0 bg-slate-100 pointer-events-none mb-96 md:mb-[40rem]"
                             aria-hidden="true"></div>

                        <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
                            <div class="py-12 md:py-20">

                                @if(!empty($landing['features']['slide']['title']) || !empty($landing['features']['slide']['subheadline']))
                                    <!-- Section header -->
                                    <div class="max-w-4xl mx-auto text-center pb-16" data-aos="fade-up">\
                                        @if(!empty($landing['features']['slide']['title']))
                                            <h2 class="h2 font-display text-slate-800 mb-4">
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
                                <div class="max-w-6xl mx-auto" data-aos="fade-up" data-aos-delay="100"
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

                                        <!-- Tab menu at top -->
                                        <div class="flex justify-center mb-12">
                                            <div class="inline-flex bg-slate-200/80 backdrop-blur-sm rounded-full p-1.5 shadow-sm">
                                                @foreach ($landing['features']['slide']['tabs'] as $index => $tab)
                                                    <button
                                                        class="px-5 py-2.5 text-sm font-medium rounded-full transition-all duration-300 focus:outline-none"
                                                        :class="tab === '{{ $index + 1 }}'
                                                            ? 'bg-white text-slate-900 shadow-md'
                                                            : 'text-slate-500 hover:text-slate-700'"
                                                        @click="selectTab('{{ $index + 1 }}')"
                                                    >
                                                        {{ $tab['tab_name'] ?? explode(' ', $tab['title'])[0] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        <!-- Tab content - titles, descriptions and images combined -->
                                        @foreach ($landing['features']['slide']['tabs'] as $index => $tab)
                                            <div x-show="tab === '{{ $index + 1 }}'"
                                                 x-transition:enter="transition ease-out duration-300"
                                                 x-transition:enter-start="opacity-0 transform translate-y-4"
                                                 x-transition:enter-end="opacity-100 transform translate-y-0">
                                                <!-- Title and description for each tab -->
                                                <div class="mb-10">
                                                    <h3 class="text-3xl md:text-4xl lg:text-5xl font-display text-slate-800 mb-6">
                                                        {{ $tab['title'] }}
                                                    </h3>
                                                    <p class="text-lg md:text-xl text-slate-600 leading-relaxed max-w-3xl mx-auto">
                                                        {{ $tab['description'] ?? 'Discover how this powerful feature can transform your workflow and enhance your productivity with our innovative solution.' }}
                                                    </p>
                                                    @if(isset($tab['features']) && is_array($tab['features']))
                                                        <div
                                                            class="flex flex-wrap justify-center gap-6 max-w-2xl mx-auto mt-6">
                                                            @foreach($tab['features'] as $feature)
                                                                <div class="flex items-center space-x-2 text-slate-600">
                                                                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0"
                                                                         fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd"
                                                                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                              clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    <span>{{ $feature }}</span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Image for each tab -->
                                                <div class="relative">
                                                    <div class="relative max-w-5xl mx-auto overflow-hidden rounded-xl border border-slate-200 shadow-lg">
                                                        <div class="aspect-[16/10] bg-slate-100">
                                                            <img class="w-full h-full object-cover object-top"
                                                                 src="{{ asset($tab['img']) }}"
                                                                 alt="{{ $tab['title'] }}"
                                                                 loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                                                 @if($index === 0) fetchpriority="high" @endif
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach

                                        <!-- Play/Pause button below -->
                                        <div class="flex justify-center mt-8">
                                            <button
                                                @click="autoSlide = !autoSlide"
                                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 rounded-full border border-slate-200 shadow-sm transition-all focus:outline-none"
                                                :aria-label="autoSlide ? 'Pause slideshow' : 'Play slideshow'"
                                            >
                                                <!-- Pause icon -->
                                                <svg x-show="autoSlide" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                                                </svg>
                                                <!-- Play icon -->
                                                <svg x-show="!autoSlide" x-cloak class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z"/>
                                                </svg>
                                                <span x-text="autoSlide ? 'Pause' : 'Play'"></span>
                                            </button>
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>
                    </section>
                @endif

{{--                @include('landing.partials.product-types')--}}

                @include('landing.partials.emotion-detection')

                @if (count($landing['features']['slide2']['slides']) !== 0)
                    <section>
                        <div class="max-w-6xl mx-auto px-4 sm:px-6">
                            <div class="py-12 md:py-20 border-t border-slate-200">

                                <!-- Section header -->
                                <div class="max-w-3xl mx-auto text-center pb-12 md:pb-20">
                                    <h2 class="h2 font-display text-slate-800">{{ $landing['features']['slide2']['title'] }}</h2>
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
                                                    <img class="md:max-w-none mx-auto rounded-sm"
                                                         src="{{ $slide['image'] ?? './images/features-home-02.png' }}"
                                                         width="540" height="620"
                                                         alt="{{ $slide['title'] ?? 'Feature slide ' . ($index + 1) }}"/>
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
                                                        <svg
                                                            class="w-4 h-4 fill-current text-blue-600 shrink-0 mt-1 mr-4"
                                                            viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                                                            <path
                                                                d="M9.4 6.6c.8.8.8 2 0 2.8-.8.8-2 .8-2.8 0-.8-.8-5-7.8-5-7.8s7 4.2 7.8 5Z"/>
                                                            <path
                                                                d="M8 16c-4.4 0-8-3.6-8-8 0-.6.4-1 1-1s1 .4 1 1c0 3.3 2.7 6 6 6s6-2.7 6-6-2.7-6-6-6c-.6 0-1-.4-1-1s.4-1 1-1c4.4 0 8 3.6 8 8s-3.6 8-8 8Z"/>
                                                        </svg>
                                                    @endif
                                                    <div>
                                                        <div
                                                            class="text-slate-800 font-medium mb-1">{{ $slide['title'] ?? 'Feature Title' }}</div>
                                                        <div
                                                            class="text-slate-500">{{ $slide['description'] ?? 'Feature description goes here.' }}</div>
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
                    <section class="relative py-16 md:py-24 bg-slate-900">
                        <div class="max-w-5xl mx-auto px-4 sm:px-6">

                            <!-- Section header -->
                            <div class="text-center mb-12 md:mb-16" data-aos="fade-up">
                                <h2 class="h2 font-display text-white mb-4">
                                    {{ $landing['features']['steps']['title'] }}
                                </h2>
                            </div>

                            <!-- Steps -->
                            <div class="grid md:grid-cols-3 gap-8 md:gap-12">

                                <!-- Step 1 -->
                                <div class="text-center" data-aos="fade-up">
                                    <div class="w-14 h-14 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                                        <span class="text-2xl font-bold text-white">1</span>
                                    </div>
                                    <h3 class="text-xl font-semibold text-white mb-3">
                                        {{ $landing['features']['steps']['steps'][0]['title'] }}
                                    </h3>
                                    @if(isset($landing['features']['steps']['steps'][0]['description']))
                                        <p class="text-slate-400">
                                            {{ $landing['features']['steps']['steps'][0]['description'] }}
                                        </p>
                                    @endif
                                </div>

                                <!-- Step 2 -->
                                <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                                    <div class="w-14 h-14 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                                        <span class="text-2xl font-bold text-white">2</span>
                                    </div>
                                    <h3 class="text-xl font-semibold text-white mb-3">
                                        {{ $landing['features']['steps']['steps'][1]['title'] }}
                                    </h3>
                                    @if(isset($landing['features']['steps']['steps'][1]['description']))
                                        <p class="text-slate-400">
                                            {{ $landing['features']['steps']['steps'][1]['description'] }}
                                        </p>
                                    @endif
                                </div>

                                <!-- Step 3 -->
                                <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                                    <div class="w-14 h-14 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                                        <span class="text-2xl font-bold text-white">3</span>
                                    </div>
                                    <h3 class="text-xl font-semibold text-white mb-3">
                                        {{ $landing['features']['steps']['steps'][2]['title'] }}
                                    </h3>
                                    @if(isset($landing['features']['steps']['steps'][2]['description']))
                                        <p class="text-slate-400">
                                            {{ $landing['features']['steps']['steps'][2]['description'] }}
                                        </p>
                                    @endif
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
                                <h2 class="h2 font-display text-slate-800 mb-3">
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
                                                <div class="h2 font-display text-slate-800">56M+</div>
                                            </div>
                                            <div class="text-slate-500 text-lg">
                                                As of 2025, Bank of America’s “Erica” chatbot handles 56 million
                                                engagements
                                                monthly and resolves 98% of queries within 44 seconds
                                            </div>
                                        </li>
                                        <li>
                                            <div class="flex items-center mb-4">
                                                <svg class="h-4 w-4 shrink-0 fill-current text-yellow-400 mr-3">
                                                    <path
                                                        d="M15.722 4.008C14.408 1.214 10.954-.635 7.318.203 5.6.596 4.072 1.561 2.919 2.757A10.57 10.57 0 0 0 .484 6.93C.03 8.458-.173 10.035.18 11.764c.191.862.518 1.683 1.146 2.479a4.876 4.876 0 0 0 2.256 1.522c1.635.469 3.156.192 4.41-.439 1.242-.615 2.298-1.769 2.494-3.094.094-.656-.537-.657-.69-.18-.781 2.126-3.715 2.534-5.265 1.579-1.568-.922-1.185-3.068-.294-4.801.89-1.729 2.454-3.02 3.92-3.338.376-.098.714-.121 1.026-.098.324.018.658.074.98.188.65.2 1.23.591 1.618 1 .27.3.575.386 1.002.461.436.061.95.117 1.499.045.535-.073 1.06-.287 1.41-.807.345-.504.462-1.348.03-2.273"/>
                                                </svg>
                                                <div class="h2 font-display text-slate-800">80%</div>
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
                                                <div class="h2 font-display text-slate-800">62%</div>
                                            </div>
                                            <div class="text-slate-500 text-lg">
                                                62% of consumers would rather use a chatbot than wait for a human
                                                customer
                                                service agent,” signaling a strong preference for the immediacy that
                                                chatbots
                                                deliver
                                            </div>
                                        </li>


                                    </ul>
                                </div>

                                <!-- Image -->
                                <div class="md:w-5/12 lg:w-1/2" data-aos="fade-left">
                                    <img class="mx-auto md:max-w-none" src="./images/target.png" width="540"
                                         height="540"
                                         alt="Target audience for AI chatbots - businesses seeking customer support automation"/>
                                </div>

                            </div>

                        </div>
                    </div>
                </section>
            @endif

            @include('landing.partials.pricing')

            <!-- FAQs -->
            @if (isset($landing['faqs']) && count($landing['faqs']) > 0)
                <section id="faqs">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6">
                        <div class="py-12 md:py-20 border-t border-slate-200">

                            <!-- Section header -->
                            <div class="max-w-3xl mx-auto text-center pb-12 md:pb-20" data-aos="fade-up">
                                <h2 class="h2 font-display text-slate-800 mb-4">
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
                                             @if($index > 0) data-aos-delay="{{ (int)$index * 100 }}" @endif>

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
                                                        <svg
                                                            class="w-5 h-5 text-slate-500 transition-transform duration-200 transform"
                                                            :class="open ? 'rotate-45' : 'rotate-0'"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
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
                                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor"
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M17 8l4 4m0 0l-4 4m4-4H3"/>
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

                        <div class="relative max-w-3xl mx-auto text-center" data-aos="fade-up">

                            <div class="absolute right-0 -mt-4 -mr-1 fill-slate-300 hidden lg:block" aria-hidden="true">
                                <svg class="fill-slate-300" width="56" height="43">
                                    <path
                                        d="M4.532 30.45C15.785 23.25 24.457 12.204 29.766.199c.034-.074-.246-.247-.3-.186-4.227 5.033-9.298 9.282-14.372 13.162C10 17.07 4.919 20.61.21 24.639c-1.173 1.005 2.889 6.733 4.322 5.81M18.96 42.198c12.145-4.05 24.12-8.556 36.631-12.365.076-.024.025-.349-.055-.347-6.542.087-13.277.083-19.982.827-6.69.74-13.349 2.24-19.373 5.197-1.53.75 1.252 7.196 2.778 6.688"/>
                                </svg>
                            </div>

                            <div class="relative">
                                <h2 class="h2 font-display text-slate-800 mb-4">
                                    {!! $landing['cta']['headline'] !!}
                                </h2>
                                <p class="text-xl text-slate-500 mb-8">
                                    {!! $landing['cta']['description'] !!}
                                </p>
                                <div>
                                    <a class="btn text-white bg-blue-600 hover:bg-blue-700 group"
                                       href="#pricing">
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

        @include('landing.partials.footer')

    </div>

    @include('landing.partials.video-modal')
@endsection
