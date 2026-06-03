@extends('landing.layouts.app')

@section('title', 'Pricing - ' . config('app.name'))

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <!-- Page content -->
        <main class="grow mt-10">
            @include('landing.partials.pricing')

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
                        'key' => 'models',
                        'question' => 'What AI models does ChatVIA use?',
                        'answer' => 'You can choose the best model for each agent and switch anytime.',
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
                <section id="faqs">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6">
                        <div class="py-12 md:py-20 border-t border-slate-200">


                            <div class="max-w-3xl mx-auto text-center pb-12 md:pb-20">
                                <h2 class="h2 font-display text-slate-800 mb-4">
                                    Frequently Asked Questions
                                </h2>
                                <p class="text-xl text-slate-500">
                                    Everything you need to know about our AI chatbot platform.
                                </p>
                            </div>

                            <div class="max-w-4xl mx-auto">
                                <div class="space-y-4">
                                    @foreach ($faqs as $index => $faq)
                                        <div class="bg-white rounded-lg border border-slate-200 shadow-sm"
                                            x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }" data-aos="fade-up"
                                            @if ($index > 0) data-aos-delay="{{ (int) $index * 100 }}" @endif>

                                            <button
                                                class="w-full text-left px-6 py-4 focus:outline-none rounded-lg transition-colors duration-200"
                                                :class="open ? 'bg-slate-50' : 'hover:bg-slate-50'" @click="open = !open"
                                                :aria-expanded="open">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="text-lg font-semibold text-slate-800 pr-4">
                                                        {{ $faq['question'] }}
                                                    </h3>
                                                    <svg class="w-5 h-5 text-slate-500 shrink-0 transition-transform duration-200"
                                                        :class="{ 'rotate-180': open }" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </div>
                                            </button>

                                            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 -translate-y-2"
                                                x-transition:enter-end="opacity-100 translate-y-0"
                                                x-transition:leave="transition ease-in duration-150"
                                                x-transition:leave-start="opacity-100 translate-y-0"
                                                x-transition:leave-end="opacity-0 -translate-y-2" class="px-6 pb-4">
                                                <div class="text-slate-600 leading-relaxed">
                                                    @if (($faq['key'] ?? '') === 'models' && ! empty($models ?? []))
                                                        @include('landing.partials.faq-models')
                                                    @else
                                                        {!! nl2br(e($faq['answer'])) !!}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                    </div>
                </section>
            @endif
        </main>
        @include('landing.partials.footer')
    </div>
@endsection
