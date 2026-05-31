@php
    $faqs = $landing['faqs'] ?? [
        [
            'question' => 'What is ChatVIA?',
            'answer' => 'ChatVIA is an AI platform that lets you create custom AI chatbots trained on your own data. Upload your documents to create AI chatbots that understand your business knowledge and can answer questions about your content.',
        ],
        [
            'question' => 'How does ChatVIA work?',
            'answer' => 'Upload your documents (PDFs, Word docs, Audio, Video, etc.) and ChatVIA processes them using advanced AI. The system creates AI chatbots that can answer questions about your content, provide insights, and help users find information quickly.',
        ],
        [
            'question' => 'Can I crawl my website?',
            'answer' => 'Yes! You can crawl your website and create a chatbot that is trained on your website content, so your customers can get instant answers to repetitive questions such as contact information, opening hours, and more.',
        ],
        [
            'question' => 'What does training mean?',
            'answer' => 'Training means we take your data and feed it through an advanced AI pipeline to understand it and create a chatbot based on the training data. This allows you to have your own custom chatbot that understands your specific business knowledge.',
        ],
        [
            'question' => 'What training technology do you use?',
            'answer' => 'We use RAG (Retrieval-Augmented Generation) technology to retrieve and ingest your data. We will never train your data or use any third party to do so, which makes your data 100% safe and private.',
        ],
        [
            'question' => 'Does it support Audio and Video files?',
            'answer' => 'Yes! ChatVIA supports both audio and video files using advanced transcription technology. The system automatically transcribes the content and makes it available for your chatbot to use.',
        ],
        [
            'question' => 'What types of files can I upload?',
            'answer' => 'ChatVIA supports PDF, Word, Text, Video, and Audio files. You can also crawl your website to add its content automatically.',
        ],
        [
            'question' => 'How many AI chatbots can I create?',
            'answer' => 'The number of chatbots you can create depends on your plan. Please refer to the pricing section for details on chatbot limits for each plan.',
        ],
        [
            'question' => 'What AI models does ChatVIA use?',
            'answer' => 'ChatVIA supports multiple open-source AI models including GPT OSS, Llama, DeepSeek, Qwen, Mistral, Gemma, and Holo2. You can choose the best model for your needs and switch between them anytime.',
        ],
        [
            'question' => 'Is my data secure and private?',
            'answer' => 'ChatVIA is GDPR compliant, hosted in the EU, and uses enterprise-grade security. Your documents and conversations remain private and encrypted. Please read our privacy policy for more information.',
        ],
        [
            'question' => "Can I customize the AI chatbot's appearance?",
            'answer' => "Yes! You can customize your agent's name, personality, and add custom branding, logos, and even use your own domain for a white-label experience.",
        ],
        [
            'question' => 'What payment methods do you accept?',
            'answer' => 'We accept all major credit cards (Visa, MasterCard, American Express) and PayPal. All payments are processed securely through Stripe. You can upgrade, downgrade, or cancel your plan anytime.',
        ],
        [
            'question' => 'Do you offer team collaboration features?',
            'answer' => 'Yes! All plans include team collaboration features. You can invite team members and share agents with your team.',
        ],
        [
            'question' => 'How fast is document processing?',
            'answer' => 'Document processing times vary based on file size and type. Most documents are processed within minutes, but larger files may take longer. You will be notified once processing is complete.',
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
                        Everything you need to know about our AI chatbot platform.
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
                                            {!! nl2br(e($faq['answer'])) !!}
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

