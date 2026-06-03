@php
    $faqs = $landing['faqs'] ?? [
        [
            'question' => 'What is Chatvia?',
            'answer' => 'Chatvia is a B2B platform for building and training your own AI agents on your data. Your agents answer from your knowledge and act in your tools — connect MCP servers, call your APIs with custom functions, and deploy anywhere.',
        ],
        [
            'question' => 'Is Chatvia just a support chatbot?',
            'answer' => 'No. Chatvia is for building AI agents for any workflow — internal knowledge assistants, research tools, data lookups, booking and ordering flows, and more. Customer support is one use case you can build, not the whole product.',
        ],
        [
            'question' => 'Can my agents call our APIs and tools?',
            'answer' => 'Yes. Define custom functions (webhooks) so an agent can call any HTTP endpoint, and connect MCP servers to give it whole toolsets at once. The agent decides when to call them during a conversation to fetch data or take action.',
        ],
        [
            'question' => 'What is MCP, and can I connect my own servers?',
            'answer' => 'MCP (Model Context Protocol) is an open standard for giving agents tools. You can connect any remote MCP server in seconds and pick which tools each agent can use — including your own internal servers.',
        ],
        [
            'question' => 'What does training mean?',
            'answer' => 'Training feeds your data through a retrieval pipeline (RAG) so the agent understands it and answers from your real content instead of guessing. We never train third-party models on your data, so it stays private.',
        ],
        [
            'question' => 'Can I train on my website, documents, audio and video?',
            'answer' => 'Yes. Upload documents (PDF, Word, Text), crawl your website, or connect data sources — and audio and video are transcribed automatically. Your agent learns from your content and stays in sync as it changes.',
        ],
        [
            'key' => 'models',
            'question' => 'What AI models does Chatvia use?',
            'answer' => 'You can choose the best model for each agent and switch anytime.',
        ],
        [
            'question' => 'How many AI agents can I create?',
            'answer' => 'It depends on your plan. See the pricing section for the number of agents included in each plan.',
        ],
        [
            'question' => 'Can I customize and white-label my agents?',
            'answer' => "Yes. Customize each agent's name, persona, branding, and logo, and serve it on your own domain for a fully white-label experience.",
        ],
        [
            'question' => 'Is my data secure and private?',
            'answer' => 'Chatvia is GDPR compliant, hosted in the EU, and uses enterprise-grade security. We never train third-party models on your data, and your documents and conversations stay private and encrypted.',
        ],
        [
            'question' => 'Do you offer team collaboration?',
            'answer' => 'Yes. All plans include team collaboration — invite team members and share agents across your team.',
        ],
        [
            'question' => 'What payment methods do you accept?',
            'answer' => 'We accept all major credit cards (Visa, MasterCard, American Express) and PayPal, processed securely through Stripe. Upgrade, downgrade, or cancel anytime.',
        ],
    ];
@endphp

<!-- FAQs -->
@if (count($faqs) > 0)
    <section id="faqs" class="bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="py-16 md:py-24">

                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center pb-12 md:pb-16" data-aos="fade-up">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-4">
                        Frequently Asked Questions
                    </h2>
                    <p class="text-lg md:text-xl text-slate-500">
                        Everything you need to know about building AI agents with Chatvia.
                    </p>
                </div>

                <!-- FAQ Content -->
                <div class="max-w-4xl mx-auto">
                    <div class="space-y-4">
                        @foreach($faqs as $index => $faq)
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
                                Still have questions?
                            </p>
                            <button onclick="window.chatvia.open()"
                                    class="inline-flex items-center text-indigo-600 hover:text-indigo-700 font-medium transition-colors duration-200 cursor-pointer">
                                Talk to our AI
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

