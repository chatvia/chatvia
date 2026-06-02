<!-- Trust & Compliance Badges -->
<section class="relative bg-slate-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="py-12 md:py-16">

            @php
                $llms = \Domain\Llm\Models\Llm::select('descriptive_name', 'description', 'icon')->get();
            @endphp

            <!-- Trust badges grid -->
            <div class="grid md:grid-cols-2 gap-6 md:gap-8 max-w-4xl mx-auto">

                <!-- EU Data Sovereignty Badge -->
                <div class="bg-white rounded-xl p-6 border border-slate-200 h-full">
                    <div class="flex flex-col items-center text-center h-full">
                        <!-- EU Flag Icon -->
                        <div
                            class="w-14 h-14 rounded-lg flex items-center justify-center mb-4 overflow-hidden border border-slate-200">
                            <img src="{{ asset('images/flags/eu.svg') }}" alt="EU Flag"
                                class="w-full h-full object-cover">
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-2">
                            Your Data Never Leaves EU
                        </h3>
                        <p class="text-slate-600 text-sm leading-relaxed mb-3">
                            100% EU-based infrastructure with data processing and storage exclusively within European
                            Union borders.
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-3 mt-auto">
                            <div class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                GDPR Compliant
                            </div>
                            <span class="text-slate-400">•</span>
                            <div class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600">
                                <img src="{{ asset('images/flags/eu.svg') }}" alt="European Union"
                                    class="w-5 h-4 rounded">
                                <span>European Union</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Open Source AI Badge -->
                <div class="bg-white rounded-xl p-6 border border-slate-200 h-full">
                    <div class="flex flex-col items-center text-center h-full">
                        <!-- AI Chip Icon -->
                        <div class="w-14 h-14 bg-emerald-600 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-2">
                            State-of-Art Open Source LLMs
                        </h3>
                        <p class="text-slate-600 text-sm leading-relaxed mb-3">
                            Powered by cutting-edge open source models. No vendor lock-in, full transparency.
                        </p>

                        <!-- AI Model Icons -->
                        <div class="flex flex-wrap justify-center gap-2 mb-3">
                            @foreach ($llms as $llm)
                                <div class="relative group">
                                    <div
                                        class="w-9 h-9 bg-slate-50 rounded border border-slate-200 p-1 transition-all duration-200 group-hover:border-emerald-400 group-hover:shadow-md cursor-pointer">
                                        <img src="{{ asset('images/llms/' . $llm->icon) }}"
                                            alt="{{ $llm->descriptive_name }}" class="w-full h-full object-contain">
                                    </div>
                                    <!-- Tooltip -->
                                    <div
                                        class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-4 py-2.5 bg-slate-900 text-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 pointer-events-none z-10 w-64">
                                        <div class="text-xs font-semibold text-emerald-400 mb-1">
                                            {{ $llm->descriptive_name }}
                                        </div>
                                        <div class="text-xs text-slate-300 leading-relaxed">
                                            {{ $llm->description }}
                                        </div>
                                        <!-- Tooltip Arrow -->
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-px">
                                            <div class="border-4 border-transparent border-t-slate-900"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 mt-auto">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.164 22 16.418 22 12c0-5.523-4.477-10-10-10z" />
                            </svg>
                            Open Source
                        </div>
                    </div>
                </div>

            </div>

            <!-- Additional trust indicators -->
            <div class="mt-8 pt-6 border-t border-slate-200">
                <div class="flex flex-wrap justify-center items-center gap-6 text-xs text-slate-600">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Zero US Data Processing</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Data Encryption at Rest</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
