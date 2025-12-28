<!-- Hero Section -->
<section class="relative overflow-hidden">
    <!-- Gradient background -->
    <div class="absolute inset-0 bg-gradient-to-b from-slate-50 via-white to-indigo-50/30 pointer-events-none"></div>

    <!-- Animated decorative elements -->
    <div class="absolute inset-0 pointer-events-none">
        <!-- Large floating circles -->
        <div class="absolute top-1/4 right-0 w-96 h-96 bg-indigo-200/60 rounded-full blur-3xl animate-float-slow"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-purple-200/50 rounded-full blur-3xl animate-float-slower">
        </div>

        <!-- Small moving circles -->
        <div class="absolute top-20 left-1/4 w-32 h-32 bg-indigo-300/50 rounded-full blur-2xl animate-float-diagonal">
        </div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 bg-purple-300/45 rounded-full blur-2xl animate-float-reverse">
        </div>
        <div
            class="absolute bottom-1/4 left-1/3 w-40 h-40 bg-indigo-200/40 rounded-full blur-2xl animate-float-slow-reverse">
        </div>

        <!-- Tiny accent circles -->
        <div class="absolute top-1/2 right-1/3 w-16 h-16 bg-indigo-400/50 rounded-full blur-xl animate-pulse-slow"></div>
        <div
            class="absolute bottom-1/3 right-1/2 w-20 h-20 bg-purple-400/45 rounded-full blur-xl animate-float-diagonal-reverse">
        </div>
    </div>

    <div class="relative max-w-5xl mx-auto px-4 sm:px-6">
        <div class="pt-28 pb-12  md:pb-20">

            <!-- Hero content -->
            <div class="text-center" data-aos="fade-up">
                <!-- Trust badge -->
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200/50 rounded-full text-sm font-medium text-slate-700 mb-6 backdrop-blur-sm">
                    <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    Privacy-first • 100% EU Hosted • Powered by Open Source LLMs
                    <svg class="w-4 h-4 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h1
                    class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold text-slate-900 mb-6 sm:mb-8 leading-tight sm:leading-[1.15] md:leading-[1.1] tracking-tight px-2 sm:px-0">
                    <span class="block">AI chatbot that knows</span>
                    <span class="block">your business <span class="relative inline-block">
                            <span class="relative z-10 text-indigo-600 font-extrabold">inside out</span>
                            <span
                                class="absolute bottom-1 left-0 right-0 h-3 sm:h-4 md:h-5 bg-gradient-to-r from-indigo-200 via-indigo-300 to-purple-200 -rotate-1 opacity-60"></span>
                        </span></span>
                </h1>

                <p
                    class="text-base sm:text-lg md:text-xl text-slate-600 mb-8 sm:mb-10 max-w-2xl mx-auto leading-relaxed px-4 sm:px-0">
                    Create AI-powered chatbots trained on your content. Provide instant 24/7 support
                    and save up to 80% of time on customer inquiries.
                </p>

                <!-- CTA Button -->
                <div class="mb-6 px-4 sm:px-0">
                    <a href="#pricing"
                        class="inline-flex items-center gap-2 px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-all duration-200 shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30 hover:-translate-y-0.5">
                        {{ $landing['hero']['button']['label'] ?? 'Get started for FREE' }}
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>

                <!-- Trust badges -->
                <div class="flex flex-wrap items-center justify-center gap-3 mb-12">
                    <!-- GDPR -->
                    <div
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white rounded-full border border-slate-200 text-sm text-slate-600">
                        <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        <span class="font-medium">GDPR Complaint</span>
                    </div>
                    <!-- Hosted in EU -->
                    <div
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white rounded-full border border-slate-200 text-sm text-slate-600">
                        <img src="{{ asset('images/flags/eu.svg') }}" alt="EU Flag" class="w-4 h-4 object-contain" />
                        <span class="font-medium">Hosted in EU</span>
                    </div>
                    <!-- Made in Denmark -->
                    <div
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white rounded-full border border-slate-200 text-sm text-slate-600">
                        <img src="{{ asset('images/flags/dk.svg') }}" alt="Denmark Flag"
                            class="w-4 h-4 object-contain" />
                        <span class="font-medium">Made in Denmark</span>
                    </div>
                </div>

                <!-- Hero Image -->
                <div class="relative max-w-5xl mx-auto" data-aos="fade-up" data-aos-delay="100" x-data="{
                    activeDemo: 'widget',
                    progress: 0,
                    isPaused: false,
                    bindProgress(videoEl, demoKey) {
                        const update = () => {
                            if (this.activeDemo !== demoKey) {
                                return;
                            }
                
                            const duration = videoEl?.duration ?? 0;
                            if (!duration || Number.isNaN(duration)) {
                                this.progress = 0;
                                return;
                            }
                
                            const current = videoEl?.currentTime ?? 0;
                            this.progress = Math.max(0, Math.min(100, (current / duration) * 100));
                        };
                
                        const updatePlayState = () => {
                            if (this.activeDemo === demoKey) {
                                this.isPaused = videoEl?.paused ?? true;
                            }
                        };
                
                        videoEl?.addEventListener('loadedmetadata', update);
                        videoEl?.addEventListener('timeupdate', update);
                        videoEl?.addEventListener('play', () => {
                            update();
                            updatePlayState();
                        });
                        videoEl?.addEventListener('pause', () => {
                            update();
                            updatePlayState();
                        });
                        videoEl?.addEventListener('seeked', update);
                    },
                    updateProgress() {
                        const videoEl = this.activeDemo === 'widget' ? this.$refs.widgetVideo : this.$refs.chatgptVideo;
                        const duration = videoEl?.duration ?? 0;
                
                        if (!duration || Number.isNaN(duration)) {
                            this.progress = 0;
                            return;
                        }
                
                        this.progress = Math.max(0, Math.min(100, ((videoEl?.currentTime ?? 0) / duration) * 100));
                    },
                    init() {
                        this.bindProgress(this.$refs.widgetVideo, 'widget');
                        this.bindProgress(this.$refs.chatgptVideo, 'chatgpt');
                        this.updateProgress();
                        this.isPaused = this.$refs.widgetVideo?.paused ?? true;
                    },
                    switchVideo(demo) {
                        this.activeDemo = demo;
                        // Stop all videos first
                        this.$refs.widgetVideo.pause();
                        this.$refs.chatgptVideo.pause();
                        // Play the selected video
                        if (demo === 'widget') {
                            this.$refs.widgetVideo.currentTime = 0;
                            this.$refs.widgetVideo.play();
                            this.isPaused = false;
                        } else {
                            this.$refs.chatgptVideo.currentTime = 0;
                            this.$refs.chatgptVideo.play();
                            this.isPaused = false;
                        }
                        this.updateProgress();
                    }
                }"
                    x-init="init()">
                    <!-- Demo Section Heading -->
                    <p class="text-center text-slate-600 font-medium mb-4">
                        Embeddable chat widget & ChatGPT-style in one platform
                    </p>

                    <!-- Video Toggle Buttons -->
                    <div class="flex items-center justify-center gap-2 mb-6">
                        <button @click="switchVideo('widget')"
                            :class="activeDemo === 'widget' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' :
                                'bg-white text-slate-600 hover:bg-slate-50'"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg font-medium text-xs transition-all duration-200 border border-slate-200 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <span>Chat Widget</span>
                        </button>
                        <button @click="switchVideo('chatgpt')"
                            :class="activeDemo === 'chatgpt' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' :
                                'bg-white text-slate-600 hover:bg-slate-50'"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg font-medium text-xs transition-all duration-200 border border-slate-200 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            <span>ChatGPT-style</span>
                        </button>
                    </div>

                    <!-- Browser-style window -->
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-slate-200/80 bg-white">
                        <!-- Window header with traffic lights -->
                        <div class="flex items-center gap-2 px-4 py-3 bg-slate-50 border-b border-slate-100">
                            <div class="flex items-center gap-1.5">
                                <div class="w-3 h-3 rounded-full bg-rose-400"></div>
                                <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                                <div class="w-3 h-3 rounded-full bg-emerald-400"></div>
                            </div>
                        </div>
                        <!-- Videos -->
                        <div class="relative">
                            <!-- Widget Demo Video -->
                            <video x-ref="widgetVideo" x-show="activeDemo === 'widget'"
                                src="https://cdn.chatvia.ai/media/howto/create-chatbot.mp4" class="w-full h-auto"
                                autoplay muted loop playsinline
                                aria-label="ChatVia Chat Widget Demo - See how to set up and embed your chat widget">
                            </video>
                            <!-- ChatGPT-like Demo Video -->
                            <video x-ref="chatgptVideo" x-show="activeDemo === 'chatgpt'"
                                src="https://cdn.chatvia.ai/media/howto/create-gpt-like-chat.mp4"
                                class="w-full h-auto" muted loop playsinline
                                aria-label="ChatVia ChatGPT-like AI Demo - See how to create ChatGPT-style chatbots">
                            </video>

                            <!-- Circular Progress Indicator -->
                            <div class="absolute top-6 left-6 group cursor-pointer"
                                @click="
                                const videoEl = activeDemo === 'widget' ? $refs.widgetVideo : $refs.chatgptVideo;
                                if (isPaused) {
                                    videoEl.play();
                                    isPaused = false;
                                } else {
                                    videoEl.pause();
                                    isPaused = true;
                                }
                            "
                                aria-label="Video progress and playback control">
                                <!-- Background circle -->
                                <svg class="w-14 h-14 transform -rotate-90 drop-shadow-lg" viewBox="0 0 100 100">
                                    <!-- Background track -->
                                    <circle cx="50" cy="50" r="42" fill="none"
                                        class="stroke-slate-300" stroke-width="6" />
                                    <!-- Progress arc -->
                                    <circle cx="50" cy="50" r="42" fill="none"
                                        class="stroke-indigo-600 transition-all duration-200" stroke-width="6"
                                        stroke-linecap="round" :stroke-dasharray="`${2 * Math.PI * 42}`"
                                        :stroke-dashoffset="`${2 * Math.PI * 42 * (1 - progress / 100)}`" />
                                </svg>

                                <!-- Play/Pause icon -->
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div
                                        class="w-10 h-10 rounded-full bg-white backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:shadow-xl group-hover:scale-105 transition-all duration-200 relative">
                                        <!-- Play icon (when paused) -->
                                        <svg x-show="isPaused" x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                            class="absolute w-5 h-5 text-indigo-600 ml-0.5" fill="currentColor"
                                            viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                        <!-- Pause icon (when playing) -->
                                        <svg x-show="!isPaused" x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                            class="absolute w-5 h-5 text-indigo-600" fill="currentColor"
                                            viewBox="0 0 24 24">
                                            <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
