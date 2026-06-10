@php
    $features = $landing['features'] ?? [
        'short' => [],
        'slide' => [
            'title' => 'See it in action',
            'subheadline' => 'Real screenshots from the product — from the first embed to the audit trail.',
            'tabs' => [
                [
                    'img' => 'images/chatvia/v2/widget.png',
                    'tab_name' => 'Chat Widget',
                    'title' => 'Deploy your agent anywhere',
                    'description' => 'Embed it as a widget, share it as a full-page hosted chat, drop in a ⌘K command bar, or reach people on Telegram and Slack. The same agent everywhere — answering from your data and your tools, here pulling live news from the web mid-conversation.',
                ],
                [
                    'img' => 'images/chatvia/v2/connectors.png',
                    'tab_name' => 'Connectors',
                    'title' => 'Plug your agent into your tools',
                    'description' => 'One click connects Perplexity web search, Slack, Gmail, Stripe, and 25+ other tools over MCP. Your agent stops just answering and starts doing.',
                ],
                [
                    'img' => 'images/chatvia/v2/control-panel.png',
                    'tab_name' => 'Control Panel',
                    'title' => 'Manage every agent in one place',
                    'description' => 'Build and run all your AI agents from one dashboard. Configure behavior, connect tools, pick models, and control every agent across your workspace.',
                ],
                [
                    'img' => 'images/chatvia/v2/inbox.png',
                    'tab_name' => 'Inbox',
                    'title' => 'Complete conversation history',
                    'description' => 'Review every conversation with sentiment, topic, and country at a glance. Spot gaps in your data, jump in with live handoff, and refine your agent where it matters.',
                ],
                [
                    'img' => 'images/chatvia/v2/pulse.png',
                    'tab_name' => 'Pulse',
                    'title' => 'Feel every conversation\'s pulse',
                    'description' => 'Each conversation gets a pulse — how it started, how it ended, and where it peaked. Catch a frustrated user before they give up, not after.',
                ],
                [
                    'img' => 'images/chatvia/v2/sentiment.png',
                    'tab_name' => 'Analytics',
                    'title' => 'Analytics & insights',
                    'description' => 'Track usage, sentiment, tone, and topics across every conversation. See exactly how your agents perform and make data-driven decisions to improve them.',
                ],
                [
                    'img' => 'images/chatvia/v2/governance.png',
                    'tab_name' => 'Governance',
                    'title' => 'Every tool call, on the record',
                    'description' => 'An immutable audit log of every action your agents take — which tool, which arguments, what outcome, how fast. Compliance-ready by default.',
                ],
                [
                    'img' => 'images/chatvia/v2/leads.png',
                    'tab_name' => 'Leads',
                    'title' => 'Capture leads in conversation',
                    'description' => 'Let your agent capture and qualify leads mid-conversation, then push them to your CRM through an MCP server or a custom function.',
                ],
            ],
        ],
        'slide2' => [
            'title' => 'Discover our unique properties',
            'subheadline' => '',
            'slides' => [],
        ],
        'steps' => [
            'title' => 'From idea to live agent in minutes',
            'steps' => [
                ['title' => 'Create', 'description' => 'Create an agent in a click — name it, pick a model, and choose how you want to deploy it.'],
                ['title' => 'Train & connect', 'description' => 'Train it on your data — upload documents, crawl your site, and connect MCP servers and custom functions so it can act in your tools.'],
                ['title' => 'Deploy', 'description' => 'Deploy anywhere — embed it, share a hosted link, or call it from your app. Your agent is live and working in your existing tools.'],
            ],
        ],
    ];
@endphp

<!-- Features Section Wrapper -->
<div id="features">
    @if (isset($features) && count($features['short']) !== 0)
        <section class="bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-16 md:py-24">

                    <!-- Items -->
                    <div class="relative max-w-sm mx-auto grid gap-16 md:grid-cols-2 lg:grid-cols-3 lg:gap-y-20 items-start md:max-w-2xl lg:max-w-none"
                        data-aos-id-blocks>

                        <!-- Lines decoration -->
                        <div class="absolute inset-0 -my-8 md:-my-12 pointer-events-none hidden md:flex"
                            aria-hidden="true">
                            <div class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100">
                            </div>
                            <div class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100">
                            </div>
                            <div class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100">
                            </div>
                            <div class="h-full w-full border-l last:border-r odd:hidden lg:odd:block border-slate-100">
                            </div>
                        </div>

                        @foreach ($features['short'] as $feature)
                            <div class="relative" data-aos="fade-up" data-aos-anchor="[data-aos-id-blocks]">
                                <div class="mb-5">
                                    <img src="{{ asset('images/icons/' . $feature['icon']) }}.svg"
                                        alt="{{ $feature['title'] }} icon" class="w-15" />
                                </div>
                                <h2 class="text-xl font-display font-semibold text-slate-900 mb-2">
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


    @if (isset($features['slide']['tabs']) && count($features['slide']['tabs']) !== 0)
        <section class="relative">
            <div class="absolute inset-0 bg-gradient-to-b from-slate-50 to-white pointer-events-none mb-96 md:mb-[40rem]"
                aria-hidden="true"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
                <div class="pt-8 pb-16 md:pt-12 md:pb-24">

                    @if (!empty($features['slide']['title']) || !empty($features['slide']['subheadline']))
                        <!-- Section header -->
                        <div class="max-w-4xl mx-auto text-center pb-16" data-aos="fade-up">
                            @if (!empty($features['slide']['title']))
                                <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-4">
                                    {{ $features['slide']['title'] }}
                                </h2>
                            @endif
                            @if (!empty($features['slide']['subheadline']))
                                <p class="text-lg md:text-xl text-slate-500">
                                    {{ $features['slide']['subheadline'] }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <!-- Section content -->
                    <div class="max-w-6xl mx-auto" data-aos="fade-up" data-aos-delay="100" x-data="{
                        tab: '1',
                        autoSlide: true,
                        manuallyPaused: false,
                        slideInterval: null,
                        progress: 0,
                        progressStart: null,
                        progressDuration: 4000,
                        progressRaf: null,
                        totalTabs: {{ count($features['slide']['tabs']) }},
                        imagesPreloaded: false,
                        init() {
                            this.preloadImages().then(() => {
                                this.imagesPreloaded = true;
                                this.$nextTick(() => {
                                    this.scrollActiveTabIntoView({ behavior: 'auto' });
                                    this.startAutoSlide();
                                });
                            });
                        },
                        preloadImages() {
                            const imageSources = [
                                @foreach($features['slide']['tabs'] as $index => $tab)
                                '{{ asset($tab['img']) }}'
                                @if(!$loop->last),
                                @endif
                                @endforeach
                            ];
                    
                            const imagePromises = imageSources.map(src => {
                                return new Promise((resolve, reject) => {
                                    const img = new Image();
                                    img.onload = () => resolve();
                                    img.onerror = () => resolve(); // Resolve even on error to prevent blocking
                                    img.src = src;
                                });
                            });
                    
                            return Promise.all(imagePromises);
                        },
                        startAutoSlide() {
                            this.stopAutoSlide();
                            this.resetProgress();
                            this.startProgress();
                    
                            this.slideInterval = setInterval(() => {
                                if (this.autoSlide) {
                                    this.nextTab();
                                }
                            }, this.progressDuration);
                        },
                        stopAutoSlide() {
                            if (this.slideInterval) {
                                clearInterval(this.slideInterval);
                                this.slideInterval = null;
                            }
                            this.stopProgress();
                        },
                        startProgress() {
                            this.stopProgress();
                    
                            if (this.progressStart === null) {
                                this.progressStart = performance.now();
                            }
                    
                            const tick = (now) => {
                                if (!this.autoSlide) {
                                    this.stopProgress();
                                    return;
                                }
                    
                                const elapsed = now - this.progressStart;
                                this.progress = Math.min(100, (elapsed / this.progressDuration) * 100);
                    
                                if (this.progress < 100) {
                                    this.progressRaf = requestAnimationFrame(tick);
                                }
                            };
                    
                            this.progressRaf = requestAnimationFrame(tick);
                        },
                        stopProgress() {
                            if (this.progressRaf) {
                                cancelAnimationFrame(this.progressRaf);
                                this.progressRaf = null;
                            }
                        },
                        resetProgress() {
                            this.progress = 0;
                            this.progressStart = performance.now();
                        },
                        setTab(tabNumber, { behavior = 'smooth' } = {}) {
                            this.tab = tabNumber.toString();
                            this.resetProgress();
                            if (this.autoSlide) {
                                this.startProgress();
                            }
                            this.$nextTick(() => this.scrollActiveTabIntoView({ behavior }));
                        },
                        nextTab() {
                            let nextTab = parseInt(this.tab) + 1;
                            if (nextTab > this.totalTabs) {
                                nextTab = 1;
                            }
                            this.setTab(nextTab, { behavior: 'smooth' });
                            if (this.autoSlide) {
                                this.startAutoSlide();
                            }
                        },
                        prevTab() {
                            let prevTab = parseInt(this.tab) - 1;
                            if (prevTab < 1) {
                                prevTab = this.totalTabs;
                            }
                            this.setTab(prevTab, { behavior: 'smooth' });
                            if (this.autoSlide) {
                                this.startAutoSlide();
                            }
                        },
                        selectTab(tabNumber) {
                            this.setTab(tabNumber, { behavior: 'smooth' });
                            if (this.autoSlide) {
                                this.startAutoSlide();
                            }
                        },
                        toggleAutoSlide() {
                            this.autoSlide = !this.autoSlide;
                            this.manuallyPaused = !this.autoSlide;
                    
                            if (this.autoSlide) {
                                this.startAutoSlide();
                            } else {
                                this.stopAutoSlide();
                            }
                        },
                        scrollActiveTabIntoView({ behavior = 'smooth' } = {}) {
                            const el = this.$refs.tabScroll;
                            if (!el) return;
                    
                            const active = el.querySelector(`[data-tab='${this.tab}']`);
                            if (!active) return;
                    
                            // Horizontal-only centering inside the scroll container.
                            // Avoids `scrollIntoView()`, which can scroll the whole page vertically in some browsers.
                            const containerRect = el.getBoundingClientRect();
                            const activeRect = active.getBoundingClientRect();
                    
                            const activeCenter = (activeRect.left - containerRect.left) + (activeRect.width / 2);
                            const targetLeft = el.scrollLeft + activeCenter - (containerRect.width / 2);
                    
                            const maxLeft = el.scrollWidth - el.clientWidth;
                            const nextLeft = Math.max(0, Math.min(maxLeft, targetLeft));
                    
                            el.scrollTo({ left: nextLeft, behavior });
                        }
                    }">

                        <!-- Single column layout -->
                        <div class="text-center">

                            <!-- Tab menu (features-bar style): fixed prev/next + scrollable pills -->
                            <div class="relative mb-12">
                                <!-- Custom CSS for hiding scrollbar (keep layout from shifting) -->
                                <style>
                                    .scrollbar-hide::-webkit-scrollbar {
                                        display: none;
                                    }

                                    .scrollbar-hide {
                                        -ms-overflow-style: none;
                                        scrollbar-width: none;
                                    }
                                </style>
                                <!-- Bar row: buttons OUTSIDE the bar, bar sits between them -->
                                <div class="flex justify-center">
                                    <div class="inline-flex items-center gap-2 sm:gap-3 max-w-full">
                                        <!-- Previous -->
                                        <div class="shrink-0">
                                            <button @click="prevTab()"
                                                class="cursor-pointer bg-white/90 hover:bg-white text-slate-700 rounded-full w-11 h-11 flex items-center justify-center shadow-lg transition-all duration-200 backdrop-blur-sm border border-slate-200"
                                                aria-label="Previous slide">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 19l-7-7 7-7" />
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Bar (pills) -->
                                        <div class="relative min-w-0 w-fit max-w-3xl lg:max-w-5xl xl:max-w-6xl">
                                            <div class="overflow-x-auto overflow-y-visible scrollbar-hide scroll-smooth px-2 py-1"
                                                x-ref="tabScroll">
                                                <div
                                                    class="flex items-center gap-3 min-w-max justify-start sm:justify-center">
                                                    <div
                                                        class="inline-flex bg-slate-100 backdrop-blur-sm rounded-full p-1.5 shadow-sm">
                                                        @foreach ($features['slide']['tabs'] as $index => $tab)
                                                            <button
                                                                class="px-4 py-2 sm:px-5 sm:py-2.5 text-xs sm:text-sm font-medium rounded-full transition-all duration-300 focus:outline-none whitespace-nowrap cursor-pointer"
                                                                data-tab="{{ $index + 1 }}"
                                                                :class="tab === '{{ $index + 1 }}' ?
                                                                    'bg-white text-slate-900 shadow-md' :
                                                                    'text-slate-500 hover:text-slate-700'"
                                                                @click="selectTab('{{ $index + 1 }}')">
                                                                {{ $tab['tab_name'] ?? explode(' ', $tab['title'])[0] }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edge fade hints (mobile only) -->
                                            <div
                                                class="absolute left-0 top-0 bottom-0 w-14 bg-gradient-to-r from-slate-50 to-transparent pointer-events-none sm:hidden">
                                            </div>
                                            <div
                                                class="absolute right-0 top-0 bottom-0 w-14 bg-gradient-to-l from-slate-50 to-transparent pointer-events-none sm:hidden">
                                            </div>
                                        </div>

                                        <!-- Next -->
                                        <div class="shrink-0">
                                            <button @click="nextTab()"
                                                class="cursor-pointer bg-white/90 hover:bg-white text-slate-700 rounded-full w-11 h-11 flex items-center justify-center shadow-lg transition-all duration-200 backdrop-blur-sm border border-slate-200"
                                                aria-label="Next slide">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab content - titles, descriptions and images combined -->
                            @foreach ($features['slide']['tabs'] as $index => $tab)
                                <div x-show="tab === '{{ $index + 1 }}'"
                                    x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0 transform translate-y-4"
                                    x-transition:enter-end="opacity-100 transform translate-y-0">
                                    <!-- Title and description for each tab -->
                                    <div class="mb-10">
                                        <h3 class="text-3xl md:text-4xl lg:text-5xl font-display text-slate-900 mb-6">
                                            {{ $tab['title'] }}
                                        </h3>
                                        <p class="text-lg md:text-xl text-slate-600 leading-relaxed max-w-3xl mx-auto">
                                            {{ $tab['description'] ?? 'Discover how this powerful feature can transform your workflow and enhance your productivity with our innovative solution.' }}
                                        </p>
                                        @if (isset($tab['features']) && is_array($tab['features']))
                                            <div class="flex flex-wrap justify-center gap-6 max-w-2xl mx-auto mt-6">
                                                @foreach ($tab['features'] as $feature)
                                                    <div class="flex items-center space-x-2 text-slate-600">
                                                        <svg class="w-5 h-5 text-indigo-600 flex-shrink-0"
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
                                        <div
                                            class="relative max-w-5xl mx-auto overflow-hidden rounded-md border border-slate-200 shadow-xl">
                                            <div class=" bg-slate-100">
                                                <img class="w-full h-full object-cover object-top"
                                                    src="{{ asset($tab['img']) }}" alt="{{ $tab['title'] }}"
                                                    loading="eager"
                                                    @if ($index === 0) fetchpriority="high" @endif />
                                            </div>

                                            <!-- Circular Progress Indicator -->
                                            <button @click="toggleAutoSlide()"
                                                class="absolute top-6 left-6 group cursor-pointer"
                                                :aria-label="autoSlide ? 'Pause slideshow' : 'Play slideshow'">
                                                <!-- Circular progress indicator -->
                                                <svg class="w-14 h-14 transform -rotate-90 drop-shadow-lg"
                                                    viewBox="0 0 100 100">
                                                    <!-- Background track -->
                                                    <circle cx="50" cy="50" r="42" fill="none"
                                                        class="stroke-slate-300" stroke-width="6" />
                                                    <!-- Progress arc -->
                                                    <circle cx="50" cy="50" r="42" fill="none"
                                                        class="stroke-indigo-600 transition-all duration-75"
                                                        stroke-width="6" stroke-linecap="round"
                                                        :stroke-dasharray="`${2 * Math.PI * 42}`"
                                                        :stroke-dashoffset="`${2 * Math.PI * 42 * (1 - progress / 100)}`" />
                                                </svg>

                                                <!-- Play/Pause icon in center -->
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-white backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:shadow-xl group-hover:scale-105 transition-all duration-200 relative">
                                                        <!-- Pause icon -->
                                                        <svg x-show="autoSlide"
                                                            x-transition:enter="transition ease-out duration-100"
                                                            x-transition:enter-start="opacity-0"
                                                            x-transition:enter-end="opacity-100"
                                                            x-transition:leave="transition ease-in duration-100"
                                                            x-transition:leave-start="opacity-100"
                                                            x-transition:leave-end="opacity-0"
                                                            class="absolute w-5 h-5 text-indigo-600"
                                                            fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                                                        </svg>
                                                        <!-- Play icon -->
                                                        <svg x-show="!autoSlide" x-cloak
                                                            x-transition:enter="transition ease-out duration-100"
                                                            x-transition:enter-start="opacity-0"
                                                            x-transition:enter-end="opacity-100"
                                                            x-transition:leave="transition ease-in duration-100"
                                                            x-transition:leave-start="opacity-100"
                                                            x-transition:leave-end="opacity-0"
                                                            class="absolute w-5 h-5 text-indigo-600 ml-0.5"
                                                            fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M8 5v14l11-7z" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                        </div>

                    </div>

                </div>
            </div>
        </section>
    @endif

    @if (isset($features['slide2']['slides']) && count($features['slide2']['slides']) !== 0)
        <section class="bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-16 md:py-24">

                    <!-- Section header -->
                    <div class="max-w-3xl mx-auto text-center pb-12 md:pb-16">
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900">
                            {{ $features['slide2']['title'] }}</h2>
                    </div>

                    <!-- Section content -->
                    <div class="max-w-xl mx-auto md:max-w-none flex flex-col md:flex-row md:items-start md:space-x-8 lg:space-x-16 xl:space-x-18 space-y-8 space-y-reverse md:space-y-0"
                        x-data="{ tab: '1' }">

                        <!-- Tabs items (images) -->
                        <div class="md:rtl md:w-5/12 lg:w-1/2 order-1 md:order-none" data-aos="fade-down">
                            <div class="relative flex flex-col">
                                @foreach ($features['slide2']['slides'] as $index => $slide)
                                    <div class="w-full" x-show="tab === '{{ $index + 1 }}'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 -translate-y-16"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 translate-y-16">
                                        <img class="md:max-w-none mx-auto rounded-xl shadow-lg"
                                            src="{{ $slide['image'] ?? './images/features-home-02.png' }}"
                                            width="540" height="620"
                                            alt="{{ $slide['title'] ?? 'Feature slide ' . ($index + 1) }}" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="md:w-7/12 lg:w-1/2" data-aos="fade-up">

                            <!-- Tabs buttons -->
                            <div class="mb-8 md:mb-0 space-y-3">
                                @foreach ($features['slide2']['slides'] as $index => $slide)
                                    <button
                                        :class="tab !== '{{ $index + 1 }}' ?
                                            'border-slate-200 opacity-60 hover:opacity-80' :
                                            'border-indigo-500 opacity-100 bg-indigo-50/50'"
                                        class="w-full flex items-start text-left bg-white border-2 px-5 py-4 rounded-xl shadow-sm transition duration-300 ease-in-out"
                                        @click="tab = '{{ $index + 1 }}'">
                                        @if (isset($slide['icon']))
                                            {!! $slide['icon'] !!}
                                        @else
                                            <svg class="w-5 h-5 fill-current text-indigo-600 shrink-0 mt-0.5 mr-4"
                                                viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M9.4 6.6c.8.8.8 2 0 2.8-.8.8-2 .8-2.8 0-.8-.8-5-7.8-5-7.8s7 4.2 7.8 5Z" />
                                                <path
                                                    d="M8 16c-4.4 0-8-3.6-8-8 0-.6.4-1 1-1s1 .4 1 1c0 3.3 2.7 6 6 6s6-2.7 6-6-2.7-6-6-6c-.6 0-1-.4-1-1s.4-1 1-1c4.4 0 8 3.6 8 8s-3.6 8-8 8Z" />
                                            </svg>
                                        @endif
                                        <div>
                                            <div class="text-slate-900 font-semibold mb-1">
                                                {{ $slide['title'] ?? 'Feature Title' }}</div>
                                            <div class="text-slate-500">
                                                {{ $slide['description'] ?? 'Feature description goes here.' }}</div>
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

    @if (isset($features['steps']['steps']) && count($features['steps']['steps']) !== 0)
        <section class="relative py-16 md:py-24 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900">
            <div class="max-w-5xl mx-auto px-4 sm:px-6">

                <!-- Section header -->
                <div class="text-center mb-12 md:mb-16" data-aos="fade-up">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-white mb-4">
                        {{ $features['steps']['title'] }}
                    </h2>
                </div>

                <!-- Steps -->
                <div class="relative grid md:grid-cols-3 gap-8 md:gap-12">

                    <!-- Dotted connectors between steps (desktop only) -->
                    <div class="hidden md:grid grid-cols-6 items-center pointer-events-none absolute top-7 inset-x-0 -translate-y-1/2"
                        aria-hidden="true">
                        <div></div>
                        <div class="ml-10 border-t-2 border-dotted border-indigo-400/50"></div>
                        <div class="mr-10 border-t-2 border-dotted border-indigo-400/50"></div>
                        <div class="ml-10 border-t-2 border-dotted border-indigo-400/50"></div>
                        <div class="mr-10 border-t-2 border-dotted border-indigo-400/50"></div>
                        <div></div>
                    </div>

                    <!-- Step 1 -->
                    <div class="text-center" data-aos="fade-up">
                        <div
                            class="w-14 h-14 bg-indigo-500 rounded-full flex items-center justify-center mx-auto mb-5">
                            <span class="text-2xl font-bold text-white">1</span>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-3">
                            {{ $features['steps']['steps'][0]['title'] }}
                        </h3>
                        @if (isset($features['steps']['steps'][0]['description']))
                            <p class="text-slate-400">
                                {{ $features['steps']['steps'][0]['description'] }}
                            </p>
                        @endif
                    </div>

                    <!-- Step 2 -->
                    <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                        <div
                            class="w-14 h-14 bg-indigo-500 rounded-full flex items-center justify-center mx-auto mb-5">
                            <span class="text-2xl font-bold text-white">2</span>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-3">
                            {{ $features['steps']['steps'][1]['title'] }}
                        </h3>
                        @if (isset($features['steps']['steps'][1]['description']))
                            <p class="text-slate-400">
                                {{ $features['steps']['steps'][1]['description'] }}
                            </p>
                        @endif
                    </div>

                    <!-- Step 3 -->
                    <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                        <div
                            class="w-14 h-14 bg-indigo-500 rounded-full flex items-center justify-center mx-auto mb-5">
                            <span class="text-2xl font-bold text-white">3</span>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-3">
                            {{ $features['steps']['steps'][2]['title'] }}
                        </h3>
                        @if (isset($features['steps']['steps'][2]['description']))
                            <p class="text-slate-400">
                                {{ $features['steps']['steps'][2]['description'] }}
                            </p>
                        @endif
                    </div>

                </div>
            </div>
        </section>
    @endif
</div>
