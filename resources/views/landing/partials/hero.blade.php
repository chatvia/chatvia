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

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
        <div class="pt-28 pb-12  md:pb-20">

            <!-- Hero content -->
            <div class="text-center max-w-6xl mx-auto">

                <!-- Trust badges -->
                <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-2.5 mb-6">
                    <!-- GDPR -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 border border-indigo-100 rounded-full text-xs sm:text-sm font-semibold text-indigo-700 uppercase tracking-wide shadow-sm">
                        <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        {{ __('landing.hero.badges.gdpr') }}
                    </span>
                    <!-- EU Hosted -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 border border-blue-100 rounded-full text-xs sm:text-sm font-semibold text-blue-700 uppercase tracking-wide shadow-sm">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                        </svg>
                        {{ __('landing.hero.badges.eu_hosted') }}
                    </span>
                    <!-- Encrypted Data -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 border border-emerald-100 rounded-full text-xs sm:text-sm font-semibold text-emerald-700 uppercase tracking-wide shadow-sm">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        {{ __('landing.hero.badges.encrypted') }}
                    </span>
                </div>

                <h1
                    class="text-6xl sm:text-7xl lg:text-8xl font-bold text-slate-900 mb-6 sm:mb-8 leading-tight sm:leading-[1.15] md:leading-[1.1] tracking-tight px-2 sm:px-0">
                    <span class="block">{{ __('landing.hero.title_line1') }}</span>
                    <span class="block mt-1">
                        <span class="relative inline-block">
                            <span class="relative z-10 text-indigo-600 font-extrabold">{{ __('landing.hero.title_make') }} {{ __('landing.hero.title_minutes') }}</span>
                            <span
                                class="absolute bottom-0.5 sm:bottom-1 left-0 right-0 h-2.5 sm:h-3 bg-linear-to-r from-indigo-200 via-indigo-300 to-purple-200 -rotate-1 opacity-60"></span>
                        </span>.
                    </span>
                </h1>

                <p
                    class="text-base sm:text-2xl md:text-xl text-slate-600 mb-5 max-w-3xl mx-auto leading-relaxed px-4 sm:px-0">
                    {{ __('landing.hero.subtitle') }}
                    <span class="block mt-3">
                        <span class="relative inline-block">
                            <span class="relative z-10 text-slate-900 font-extrabold">{{ __('landing.hero.no_code') }}</span>
                            <span
                                class="absolute bottom-1 left-0 right-0 h-2.5 sm:h-3 bg-linear-to-r from-yellow-200 via-yellow-300 to-amber-200 -rotate-1 opacity-80"></span>
                        </span>
                    </span>
                </p>

                <!-- EU hosting proof: GDPR seal + the countries we run in -->
                <div class="flex items-center justify-center gap-4 mb-8 sm:mb-10">
                    <img src="{{ asset('images/gdpr-compliant.svg') }}" alt="{{ __('landing.hero.gdpr_alt') }}" class="h-12 sm:h-14 w-auto" />
                    <div class="h-8 w-px bg-slate-200" aria-hidden="true"></div>
                    <div class="flex items-center gap-1.5">
                        <img src="{{ asset('images/germany.svg') }}" alt="Germany" title="Germany"
                            class="w-9 h-9 sm:w-10 sm:h-10 rounded-full" />
                        <img src="{{ asset('images/france.svg') }}" alt="France" title="France"
                            class="w-9 h-9 sm:w-10 sm:h-10 rounded-full" />
                        <img src="{{ asset('images/denmark.svg') }}" alt="Denmark" title="Denmark"
                            class="w-9 h-9 sm:w-10 sm:h-10 rounded-full" />
                    </div>
                </div>

                <!-- CTA buttons: primary trial + secondary demo (B2B buyers rarely self-serve on impulse) -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 px-4 sm:px-0">
                    <a href="#pricing"
                        class="inline-flex items-center gap-2 px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-all duration-200 shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30 hover:-translate-y-0.5">
                        {{ __('landing.hero.cta') }}
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 rtl:-scale-x-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                    <a href="{{ route('contact') }}"
                        class="inline-flex items-center gap-2 px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-xl border border-slate-200 hover:border-slate-300 transition-all duration-200">
                        {{ __('landing.hero.cta_secondary') }}
                    </a>
                </div>

            </div>

            <!-- Hero Image -->
            <div class="relative max-w-7xl mx-auto mt-14 sm:mt-16">
                <!-- Glow behind image -->
                <div class="absolute -inset-6 bg-gradient-to-r from-indigo-500/20 via-purple-500/20 to-pink-500/20 blur-3xl"></div>

                <!-- Image container -->
                <div class="relative overflow-hidden shadow-2xl ring-1 ring-slate-900/10 bg-white">
                    <img src="{{ asset('images/main.png') }}"
                        alt="ChatVia Agent Statistics Dashboard - Track conversations, messages, and customer sentiment"
                        class="w-full h-auto" />
                </div>
            </div>

        </div>
    </div>

    <style>
        /* Soft drifting motion for the hero background accents. Tailwind v4 dropped
           the old config keyframes, so they're defined here (like the marquee). */
        @keyframes hero-float-a { 0%, 100% { transform: translate(0, 0); }   50% { transform: translate(24px, -28px); } }
        @keyframes hero-float-b { 0%, 100% { transform: translate(0, 0); }   50% { transform: translate(-30px, 24px); } }
        @keyframes hero-float-c { 0%, 100% { transform: translate(0, 0); }   50% { transform: translate(26px, 22px); } }
        @keyframes hero-float-d { 0%, 100% { transform: translate(0, 0); }   50% { transform: translate(-22px, -24px); } }
        @keyframes hero-float-e { 0%, 100% { transform: translate(0, 0); }   50% { transform: translate(28px, -20px); } }
        @keyframes hero-float-f { 0%, 100% { transform: translate(0, 0); }   50% { transform: translate(-26px, -22px); } }
        @keyframes hero-pulse   { 0%, 100% { transform: scale(1); opacity: .5; } 50% { transform: scale(1.15); opacity: .8; } }

        .animate-float-slow             { animation: hero-float-a 16s ease-in-out infinite; will-change: transform; }
        .animate-float-slower           { animation: hero-float-b 22s ease-in-out infinite; will-change: transform; }
        .animate-float-diagonal         { animation: hero-float-c 18s ease-in-out infinite; will-change: transform; }
        .animate-float-reverse          { animation: hero-float-d 20s ease-in-out infinite; will-change: transform; }
        .animate-float-slow-reverse     { animation: hero-float-e 24s ease-in-out infinite; will-change: transform; }
        .animate-float-diagonal-reverse { animation: hero-float-f 19s ease-in-out infinite; will-change: transform; }
        .animate-pulse-slow             { animation: hero-pulse 10s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
            .animate-float-slow, .animate-float-slower, .animate-float-diagonal,
            .animate-float-reverse, .animate-float-slow-reverse,
            .animate-float-diagonal-reverse, .animate-pulse-slow { animation: none; }
        }
    </style>
</section>
