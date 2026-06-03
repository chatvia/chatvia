<!-- CTA Section -->
<section class="relative overflow-hidden">
    <!-- Gradient background -->
    <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-purple-50"></div>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
        <div class="py-20 md:py-28">

            <div class="relative max-w-3xl mx-auto text-center" data-aos="fade-up">

                <div class="relative">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-5">
                        {!! $landing['cta']['headline'] ?? 'Ship your first AI agent in <span class="text-emerald-500">minutes</span>.' !!}
                    </h2>
                    <p class="text-lg md:text-xl text-slate-600 mb-10 max-w-2xl mx-auto">
                        {!! $landing['cta']['description'] ?? 'Build agents trained on your data that connect MCP servers, call your APIs, and act in your existing tools — no code, no engineers.' !!}
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a class="inline-flex items-center gap-2 px-8 py-4 text-lg font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-all duration-200 shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30 hover:-translate-y-0.5"
                           href="#pricing">
                            {{ $landing['cta']['button']['label'] ?? 'Start your free trial' }}
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>
                        @if(($landing['has-widget'] ?? true) === true)
                            <button onclick="window.chatvia.open()"
                                    class="inline-flex items-center gap-2 px-8 py-4 text-lg font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-xl border border-slate-200 transition-all duration-200 hover:shadow-md cursor-pointer">
                                Talk to our AI
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

            </div>

        </div>
    </div>
</section>

