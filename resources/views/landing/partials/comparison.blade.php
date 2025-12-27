<!-- Comparison Table Section -->
<a id="why-chatvia"></a>
<section class="relative bg-gradient-to-b from-white via-slate-50 to-white mb-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div>
            <!-- Section header -->
            <div class="max-w-3xl mx-auto text-center pb-12 md:pb-16" data-aos="fade-up">
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-4">
                    Why choose ChatVia?
                </h2>
                <p class="text-lg md:text-xl text-slate-600">
                    See how we compare to other chatbot platforms
                </p>
            </div>

            <!-- Comparison Table -->
            <div class="max-w-5xl mx-auto" data-aos="fade-up" data-aos-delay="100">
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full align-middle">
                        <div class="overflow-hidden border border-slate-200 shadow-xl">
                            @php
                                $featureIcon = <<<'SVG'
                                <svg class="w-6 h-6 text-indigo-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                SVG;

                                $otherIcon = <<<'SVG'
                                <div class="inline-flex items-center justify-center">
                                    <svg class="w-7 h-7 text-slate-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                SVG;
                            @endphp
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700">
                                    <tr>
                                        <th scope="col" class="py-5 px-6 text-left text-lg font-semibold text-white">
                                            Features
                                        </th>
                                        <th scope="col"
                                            class="py-5 px-6 text-center text-sm font-semibold text-white">
                                            <div class="flex flex-col items-center">
                                                <span class="text-lg font-bold">ChatVia</span>
                                                <span
                                                    class="mt-1 px-3 py-1 text-xs font-medium bg-emerald-500 text-white rounded-full">Best
                                                    Value</span>
                                            </div>
                                        </th>
                                        <th scope="col"
                                            class="py-5 px-6 text-center text-sm font-semibold text-indigo-100">
                                            Others
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100">
                                    <!-- Open Source Models -->
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Open Source AI
                                                        Models</div>
                                                    <div class="text-xs text-slate-500 mt-1">100% privacy, your data
                                                        never leaves your control</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- Agentic ingestion & chunking -->
                                    <tr class="bg-indigo-50/30 hover:bg-indigo-50/50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Agentic ingestion
                                                        & chunking</div>
                                                    <div class="text-xs text-slate-500 mt-1">AI pipeline extracts
                                                        context and structure (not naive splitting)</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- Hybrid search -->
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Hybrid search
                                                    </div>
                                                    <div class="text-xs text-slate-500 mt-1">Vector + BM25 to retrieve
                                                        the best results</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- Tables & images -->
                                    <tr class="bg-indigo-50/30 hover:bg-indigo-50/50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Tables & images
                                                        (retrievable)</div>
                                                    <div class="text-xs text-slate-500 mt-1">Understands complex
                                                        documents and makes them searchable</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- Multi-layer retrieval -->
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Multi-layer
                                                        retrieval</div>
                                                    <div class="text-xs text-slate-500 mt-1">Re-ranking + guardrails
                                                        for fast, accurate answers</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- All-in-One Platform -->
                                    <tr class="bg-indigo-50/30 hover:bg-indigo-50/50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">All-in-One
                                                        Platform</div>
                                                    <div class="text-xs text-slate-500 mt-1">Widget + Chat UI +
                                                        knowledge management</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- Tone analysis -->
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Tone analysis
                                                    </div>
                                                    <div class="text-xs text-slate-500 mt-1">Detects tone shifts and
                                                        adapts responses</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- Sentiment analysis -->
                                    <tr class="bg-indigo-50/30 hover:bg-indigo-50/50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Sentiment
                                                        analysis
                                                    </div>
                                                    <div class="text-xs text-slate-500 mt-1">Flags frustrated users for
                                                        better outcomes</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>

                                    <!-- Human handoff -->
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="py-5 px-6">
                                            <div class="flex items-start">
                                                {!! $featureIcon !!}
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Handoff</div>
                                                    <div class="text-xs text-slate-500 mt-1">Seamlessly escalate to a
                                                        human with full context</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div class="inline-flex items-center justify-center">
                                                <svg class="w-7 h-7 text-emerald-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            {!! $otherIcon !!}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</section>
