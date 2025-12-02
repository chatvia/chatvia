<!-- FAQs -->
@if (isset($landing['faqs']) && count($landing['faqs']) > 0)
    <section id="faqs" class="bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="py-16 md:py-24">

                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center pb-12 md:pb-16" data-aos="fade-up">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-4">
                        Frequently Asked Questions
                    </h2>
                    <p class="text-lg md:text-xl text-slate-500">
                        Everything you need to know about our AI chatbot platform.
                    </p>
                </div>

                <!-- FAQ Content -->
                <div class="max-w-4xl mx-auto">
                    <div class="space-y-4">
                        @foreach($landing['faqs'] as $index => $faq)
                            <div class="bg-slate-50 rounded-xl border border-slate-100"
                                 x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }"
                                 data-aos="fade-up"
                                 @if($index > 0) data-aos-delay="{{ (int)$index * 100 }}" @endif>

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
                                            {!! nl2br(e($faq['answer'])) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(isset($landing['has-widget']) && $landing['has-widget'] === true)
                        <div class="text-center mt-12">
                            <p class="text-lg text-slate-600 mb-4">
                                Still have questions?
                            </p>
                            <button onclick="window.chatvia.open()"
                                    class="inline-flex items-center text-indigo-600 hover:text-indigo-700 font-medium transition-colors duration-200 cursor-pointer">
                                Get in touch with our support team
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor"
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

