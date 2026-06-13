<!-- Feature highlight cards -->
<section class="relative bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="pt-10 md:pt-14 pb-16 md:pb-24">

            <!-- Section header -->
            <div class="max-w-3xl mx-auto text-center pb-12 md:pb-16">
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-4">
                    {{ __('landing.cards.header_title') }}
                </h2>
                <p class="text-lg md:text-xl text-slate-500">
                    {{ __('landing.cards.header_subtitle') }}
                </p>
            </div>

            <!-- Cards -->
            <div class="grid gap-6 md:grid-cols-3 items-stretch">

                <!-- Card 1: Train on your own data -->
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl overflow-hidden flex flex-col">
                    <div class="h-64 px-6 flex items-center justify-center">
                        <div class="w-full max-w-[300px] bg-white rounded-xl shadow-lg shadow-slate-900/5 ring-1 ring-slate-900/5 overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('landing.cards.sources') }}</span>
                                <span class="text-[11px] font-medium text-indigo-600">{{ __('landing.cards.add_source') }}</span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                <div class="px-4 py-2.5 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                    </div>
                                    <div class="min-w-0 grow">
                                        <div class="text-[13px] font-medium text-slate-700 truncate">pricing-2026.pdf</div>
                                        <div class="text-[11px] text-slate-400">{{ __('landing.cards.demo_pages') }}</div>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 shrink-0">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        {{ __('landing.cards.trained') }}
                                    </span>
                                </div>
                                <div class="px-4 py-2.5 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m-18.432 0A8.959 8.959 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                                    </div>
                                    <div class="min-w-0 grow">
                                        <div class="text-[13px] font-medium text-slate-700 truncate">docs.acme.com</div>
                                        <div class="text-[11px] text-slate-400">{{ __('landing.cards.demo_crawled') }}</div>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 shrink-0">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        {{ __('landing.cards.trained') }}
                                    </span>
                                </div>
                                <div class="px-4 py-2.5 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                                    </div>
                                    <div class="min-w-0 grow">
                                        <div class="text-[13px] font-medium text-slate-700 truncate">{{ __('landing.cards.demo_product_faq') }}</div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="grow h-1 rounded-full bg-slate-100 overflow-hidden">
                                                <div class="w-2/3 h-full rounded-full bg-indigo-500"></div>
                                            </div>
                                            <span class="text-[10px] text-slate-400 shrink-0">28/42</span>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-indigo-600 shrink-0">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                                        {{ __('landing.cards.training') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-8 pb-8 grow">
                        <h3 class="text-xl font-semibold text-slate-900 mb-3">{{ __('landing.cards.card1_title') }}</h3>
                        <p class="text-slate-600 leading-relaxed">
                            {{ __('landing.cards.card1_desc') }}
                        </p>
                    </div>
                </div>

                <!-- Card 2: Acts in your tools -->
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl overflow-hidden flex flex-col">
                    <div class="h-64 px-6 flex items-center justify-center">
                        <div class="w-full max-w-[300px] bg-white rounded-xl shadow-lg shadow-slate-900/5 ring-1 ring-slate-900/5 overflow-hidden">
                            <div class="flex items-center gap-2.5 px-4 py-2 border-b border-slate-100">
                                <div class="w-6 h-6 rounded-full bg-indigo-600 text-white text-[11px] font-semibold flex items-center justify-center shrink-0">A</div>
                                <span class="text-[12px] font-semibold text-slate-700">Acme Assistant</span>
                                <span class="ms-auto inline-flex items-center gap-1 text-[10px] text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ __('landing.cards.online') }}
                                </span>
                            </div>
                            <div class="p-3 space-y-2">
                                <div class="ms-auto w-fit max-w-[85%] bg-indigo-600 text-white text-[12px] leading-snug px-3 py-2 rounded-2xl rounded-br-md rtl:rounded-br-2xl rtl:rounded-bl-md">
                                    {{ __('landing.cards.demo_book') }}
                                </div>
                                <div class="flex items-center gap-2.5 border border-slate-200 rounded-lg px-3 py-2">
                                    <img src="{{ asset('images/logos/connectors/google-calendar.svg') }}" alt="" aria-hidden="true" class="w-5 h-5 shrink-0" />
                                    <div class="min-w-0 grow">
                                        <div class="text-[12px] font-medium text-slate-700 truncate">Google Calendar</div>
                                        <div class="text-[10px] text-slate-400 truncate">{{ __('landing.cards.demo_booking') }}</div>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 shrink-0">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        {{ __('landing.cards.done') }}
                                    </span>
                                </div>
                                <div class="w-fit max-w-[85%] bg-slate-100 text-slate-700 text-[12px] leading-snug px-3 py-2 rounded-2xl rounded-bl-md rtl:rounded-bl-2xl rtl:rounded-br-md">
                                    {{ __('landing.cards.demo_booked') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-8 pb-8 grow">
                        <h3 class="text-xl font-semibold text-slate-900 mb-3">{{ __('landing.cards.card2_title') }}</h3>
                        <p class="text-slate-600 leading-relaxed">
                            {{ __('landing.cards.card2_desc') }}
                        </p>
                    </div>
                </div>

                <!-- Card 3: Advanced reporting -->
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl overflow-hidden flex flex-col">
                    <div class="h-64 px-6 flex items-center justify-center">
                        <div class="w-full max-w-[300px] bg-white rounded-xl shadow-lg shadow-slate-900/5 ring-1 ring-slate-900/5 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[12px] font-semibold text-slate-700">{{ __('landing.cards.demo_topics') }}</span>
                                <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                                    {{ __('landing.cards.last_7_days') }}
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </span>
                            </div>
                            <div class="relative">
                                <div class="absolute left-[34%] -translate-x-1/2 -top-2 z-10 flex items-center gap-1.5 rounded-md bg-slate-900 px-2 py-1 shadow-lg whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                                    <span class="text-[10px] font-medium text-slate-300">{{ __('landing.cards.demo_account_setup') }}</span>
                                    <span class="text-[10px] font-semibold text-white">643</span>
                                </div>
                                <svg viewBox="0 0 260 100" class="w-full" fill="none" aria-hidden="true">
                                    <line x1="0" y1="18" x2="260" y2="18" stroke="#e2e8f0" stroke-dasharray="3 3"/>
                                    <line x1="0" y1="42" x2="260" y2="42" stroke="#e2e8f0" stroke-dasharray="3 3"/>
                                    <line x1="0" y1="66" x2="260" y2="66" stroke="#e2e8f0" stroke-dasharray="3 3"/>
                                    <line x1="88" y1="14" x2="88" y2="78" stroke="#cbd5e1" stroke-dasharray="3 3"/>
                                    <path d="M4 52 C 18 48, 32 38, 46 34 C 60 30, 74 22, 88 20 C 102 18, 116 36, 130 44 C 144 50, 158 42, 172 38 C 186 34, 200 33, 214 30 C 228 27, 242 26, 256 24 L 256 78 L 4 78 Z" fill="#4f46e5" fill-opacity="0.06"/>
                                    <path d="M4 52 C 18 48, 32 38, 46 34 C 60 30, 74 22, 88 20 C 102 18, 116 36, 130 44 C 144 50, 158 42, 172 38 C 186 34, 200 33, 214 30 C 228 27, 242 26, 256 24" stroke="#4f46e5" stroke-width="2" stroke-linecap="round"/>
                                    <circle cx="88" cy="20" r="3.5" fill="#4f46e5" stroke="#fff" stroke-width="1.5"/>
                                    <text x="4" y="94" font-size="8" fill="#94a3b8">Mon</text>
                                    <text x="46" y="94" font-size="8" fill="#94a3b8" text-anchor="middle">Tue</text>
                                    <text x="88" y="94" font-size="8" fill="#94a3b8" text-anchor="middle">Wed</text>
                                    <text x="130" y="94" font-size="8" fill="#94a3b8" text-anchor="middle">Thu</text>
                                    <text x="172" y="94" font-size="8" fill="#94a3b8" text-anchor="middle">Fri</text>
                                    <text x="214" y="94" font-size="8" fill="#94a3b8" text-anchor="middle">Sat</text>
                                    <text x="256" y="94" font-size="8" fill="#94a3b8" text-anchor="end">Sun</text>
                                </svg>
                            </div>
                            <div class="flex items-center gap-2 border-t border-slate-100 mt-3 pt-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" /></svg>
                                    {{ __('landing.cards.demo_positive') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-medium text-rose-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.498 15.25H4.372c-1.026 0-1.945-.694-2.054-1.715a12.137 12.137 0 0 1-.068-1.285c0-2.848.992-5.464 2.649-7.521C5.287 4.247 5.886 4 6.504 4h4.016a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23h1.294M7.498 15.25c.618 0 .991.724.725 1.282A7.471 7.471 0 0 0 7.5 19.75 2.25 2.25 0 0 0 9.75 22a.75.75 0 0 0 .75-.75v-.633c0-.573.11-1.14.322-1.672.304-.76.93-1.33 1.653-1.715a9.04 9.04 0 0 0 2.86-2.4c.498-.634 1.226-1.08 2.032-1.08h.384m-10.253 1.5H9.7m8.075-9.75c.01.05.027.1.05.148.593 1.2.925 2.55.925 3.977 0 1.487-.36 2.89-.999 4.125m.023-8.25c-.076-.365.183-.75.575-.75h.908c.889 0 1.713.518 1.972 1.368.339 1.11.521 2.287.521 3.507 0 1.553-.295 3.036-.831 4.398-.306.774-1.086 1.227-1.918 1.227h-1.053c-.472 0-.745-.556-.5-.96a8.95 8.95 0 0 0 .303-.54" /></svg>
                                    {{ __('landing.cards.demo_negative') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="px-8 pb-8 grow">
                        <h3 class="text-xl font-semibold text-slate-900 mb-3">{{ __('landing.cards.card3_title') }}</h3>
                        <p class="text-slate-600 leading-relaxed">
                            {{ __('landing.cards.card3_desc') }}
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
