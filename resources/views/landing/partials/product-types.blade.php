<!-- Two Product Types Section -->
<section class="relative py-16 md:py-24 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">

        <!-- Section header -->
        <div class="text-center mb-12 md:mb-16" data-aos="fade-up">
            <h2 class="h2 font-display text-slate-900 mb-4">
                Two ways to deploy your AI
            </h2>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto">
                Choose the experience that fits your needs. Both options are powered by your custom knowledge base.
            </p>
        </div>

        <!-- Tab switcher -->
        <div x-data="{ activeType: 'widget' }" data-aos="fade-up" data-aos-delay="100">

            <!-- Tab menu -->
            <div class="flex justify-center mb-10">
                <div class="inline-flex bg-slate-200/80 backdrop-blur-sm rounded-full p-1.5 shadow-sm">
                    <button
                        class="px-6 py-2.5 text-sm font-medium rounded-full transition-all duration-300 focus:outline-none"
                        :class="activeType === 'widget'
                            ? 'bg-white text-slate-900 shadow-md'
                            : 'text-slate-500 hover:text-slate-700'"
                        @click="activeType = 'widget'"
                    >
                        Chat Widget
                    </button>
                    <button
                        class="px-6 py-2.5 text-sm font-medium rounded-full transition-all duration-300 focus:outline-none"
                        :class="activeType === 'knowledge'
                            ? 'bg-white text-slate-900 shadow-md'
                            : 'text-slate-500 hover:text-slate-700'"
                        @click="activeType = 'knowledge'"
                    >
                        Knowledge Base
                    </button>
                </div>
            </div>

            <!-- Content area -->
            <div class="max-w-5xl mx-auto">

                <!-- Widget content -->
                <div x-show="activeType === 'widget'"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-4"
                     x-transition:enter-end="opacity-100 transform translate-y-0">

                    <!-- Title and description -->
                    <div class="text-center mb-10">
                        <div class="inline-flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-display text-slate-900 mb-4">
                            Chat Widget
                        </h3>
                        <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-6">
                            Embed a powerful AI chat bubble on your website. Engage visitors 24/7 and capture leads while you sleep.
                        </p>
                        <div class="flex flex-wrap justify-center gap-4 text-sm text-slate-600">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Floating chat bubble
                            </span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Lead capture & tracking
                            </span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                One-line embed code
                            </span>
                        </div>
                    </div>

                    <!-- Image -->
                    <div class="relative">
                        <div class="overflow-hidden rounded-xl border border-slate-200 shadow-lg bg-slate-100">
                            <img
                                src="{{ asset('images/chatvia/widget.png') }}"
                                alt="Chat Widget Interface"
                                class="w-full h-auto"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>

                <!-- Knowledge Base content -->
                <div x-show="activeType === 'knowledge'"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-4"
                     x-transition:enter-end="opacity-100 transform translate-y-0">

                    <!-- Title and description -->
                    <div class="text-center mb-10">
                        <div class="inline-flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-display text-slate-900 mb-4">
                            Knowledge Base
                        </h3>
                        <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-6">
                            A ChatGPT-like experience trained on your data. Perfect for internal teams, documentation portals, and self-service knowledge bases.
                        </p>
                        <div class="flex flex-wrap justify-center gap-4 text-sm text-slate-600">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Full-page conversational interface
                            </span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Shareable link for your team
                            </span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Custom branding & domain
                            </span>
                        </div>
                    </div>

                    <!-- Image -->
                    <div class="relative">
                        <div class="overflow-hidden rounded-xl border border-slate-200 shadow-lg bg-slate-100">
                            <img
                                src="{{ asset('images/chatvia/gpt.png') }}"
                                alt="GPT-like Knowledge Base Interface"
                                class="w-full h-auto"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
