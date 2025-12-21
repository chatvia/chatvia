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
        <div class="pt-32 pb-12 md:pt-40 md:pb-20">

            <!-- Hero content -->
            <div class="text-center" data-aos="fade-up">
                <!-- Trust badge -->
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200/50 rounded-full text-sm font-medium text-slate-700 mb-6 backdrop-blur-sm">
                    <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    Privacy-first • 100% EU Hosted • Powered by Open Source AI
                    <svg class="w-4 h-4 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                <div class="relative max-w-5xl mx-auto" data-aos="fade-up" data-aos-delay="100">
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
                        <!-- Image with fade out effect -->
                        <div class="relative">
                            <img src="{{ asset('images/agent-stats.png') }}"
                                alt="ChatVia AI Chatbot Dashboard - Analytics and conversation management"
                                class="w-full h-auto" />
                            <!-- Fade out gradient overlay -->
                            <div
                                class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-white via-white/80 to-transparent">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
