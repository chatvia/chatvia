@php
    $faqs = $landing['faqs'] ?? [
        [
            'question' => __('landing.faqs.items.what.question'),
            'answer' => __('landing.faqs.items.what.answer'),
        ],
        [
            'question' => __('landing.faqs.items.support_bot.question'),
            'answer' => __('landing.faqs.items.support_bot.answer'),
        ],
        [
            'question' => __('landing.faqs.items.apis.question'),
            'answer' => __('landing.faqs.items.apis.answer'),
        ],
        [
            'question' => __('landing.faqs.items.mcp.question'),
            'answer' => __('landing.faqs.items.mcp.answer'),
        ],
        [
            'question' => __('landing.faqs.items.training.question'),
            'answer' => __('landing.faqs.items.training.answer'),
        ],
        [
            'question' => __('landing.faqs.items.sources.question'),
            'answer' => __('landing.faqs.items.sources.answer'),
        ],
        [
            'key' => 'models',
            'question' => __('landing.faqs.items.models.question'),
            'answer' => __('landing.faqs.items.models.answer'),
        ],
        [
            'question' => __('landing.faqs.items.agent_count.question'),
            'answer' => __('landing.faqs.items.agent_count.answer'),
        ],
        [
            'question' => __('landing.faqs.items.white_label.question'),
            'answer' => __('landing.faqs.items.white_label.answer'),
        ],
        [
            'question' => __('landing.faqs.items.security.question'),
            'answer' => __('landing.faqs.items.security.answer'),
        ],
        [
            'question' => __('landing.faqs.items.team.question'),
            'answer' => __('landing.faqs.items.team.answer'),
        ],
        [
            'question' => __('landing.faqs.items.payment.question'),
            'answer' => __('landing.faqs.items.payment.answer'),
        ],
    ];
@endphp

<!-- FAQs -->
@if (count($faqs) > 0)
    <section id="faqs" class="bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="py-16 md:py-24">

                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center pb-12 md:pb-16">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-4">
                        {{ __('landing.faqs.heading') }}
                    </h2>
                    <p class="text-lg md:text-xl text-slate-500">
                        {{ __('landing.faqs.subtitle') }}
                    </p>
                </div>

                <!-- FAQ Content -->
                <div class="max-w-4xl mx-auto">
                    <div class="space-y-4">
                        @foreach($faqs as $index => $faq)
                            <div class="bg-slate-50 rounded-xl border border-slate-100"
                                 x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }"
                                 @if($index > 0) @endif>

                                <!-- Question -->
                                <button
                                    class="w-full text-left px-6 py-5 focus:outline-none rounded-xl transition-colors duration-200"
                                    :class="open ? 'bg-indigo-50/50' : 'hover:bg-slate-100'"
                                    @click="open = !open"
                                    :aria-expanded="open">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-slate-900 pr-4">
                                            {{ $faq['question'] }}
                                        </h3>
                                        <div class="flex-shrink-0">
                                            <svg
                                                class="w-5 h-5 text-indigo-600 transition-transform duration-200 transform"
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
                                    <div class="px-6 pb-5">
                                        <div class="text-slate-600 leading-relaxed">
                                            @if (($faq['key'] ?? '') === 'models' && ! empty($models ?? []))
                                                @include('landing.partials.faq-models')
                                            @else
                                                {!! nl2br(e($faq['answer'])) !!}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(($landing['has-widget'] ?? true) === true)
                        <div class="text-center mt-12">
                            <p class="text-lg text-slate-600 mb-4">
                                {{ __('landing.faqs.still_questions') }}
                            </p>
                            <button onclick="window.chatvia.open()"
                                    class="inline-flex items-center text-indigo-600 hover:text-indigo-700 font-medium transition-colors duration-200 cursor-pointer">
                                {{ __('landing.faqs.talk_to_ai') }}
                                <svg class="w-4 h-4 ms-2 rtl:-scale-x-100" fill="none" stroke="currentColor"
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

