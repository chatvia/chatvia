<!-- FAQs -->
<section id="faqs">
    <div class="py-12 md:py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="max-w-2xl mx-auto mb-12">
                <div class="space-y-2">
                    @php
                        $faqs = [
                            [
                                'question' => 'What is ChatVIA?',
                                'answer' => 'ChatVIA is an AI platform that lets you create custom AI agents trained on your own data. Upload your documents to create AI chatbots that understand your business knowledge and can answer questions about your content.',
                                'expanded' => true
                            ],
                            [
                                'question' => 'How does ChatVIA work?',
                                'answer' => 'Upload your documents (PDFs, Word docs, spreadsheets, etc.) and ChatVIA processes them using advanced AI. The system creates AI agents that can answer questions about your content, provide insights, and help users find information quickly.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'What types of files can I upload?',
                                'answer' => 'Currently PDF and Word files are supported, however I\'m working hard to support 44+ file types including Excel spreadsheets, PowerPoint presentations, images, videos, and more.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'How many AI agents can I create?',
                                'answer' => 'It depends on your plan. Please refer to the pricing secion.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'What AI models does ChatVIA use?',
                                'answer' => 'ChatVIA supports multiple leading AI models including GPT, Claude, Gemini, Mistral, Cohere, LLaMA, and more. You can choose the best model for your needs and switch between them anytime.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'Is my data secure and private?',
                                'answer' => 'ChatVIA is GDPR compliant, hosted in the EU, and uses enterprise-grade security. Your documents and conversations remain private and encrypted. Please read our privacy policy for more information.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'Can I customize the AI agent\'s appearance?',
                                'answer' => 'Yes! You can customize your agent\'s name, instructions, and you can also add custom branding, logos, and even use your own domain for a white-label experience.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'What payment methods do you accept?',
                                'answer' => 'We accept all major credit cards (Visa, MasterCard, American Express) and PayPal. All payments are processed securely through Stripe. You can upgrade, downgrade, or cancel your plan anytime.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'Do you offer team collaboration features?',
                                'answer' => 'Yes! All the plans include team collaboration. You can invite team members and share agents.',
                                'expanded' => false
                            ],
                        ];
                    @endphp

                    @foreach($faqs as $index => $faq)
                        @php
                            $faqId = $index + 1;
                            $titleId = "faqs-title-{$faqId}";
                            $textId = "faqs-text-{$faqId}";
                            $expandedState = $faq['expanded'] ? 'true' : 'false';
                        @endphp

                        <!-- Accordion item -->
                        <div x-data="{ expanded: {{ $expandedState }} }" class="bg-zinc-100 rounded-sm">
                            <h2>
                                <button
                                        id="{{ $titleId }}"
                                        class="flex items-center justify-between w-full font-inter-tight text-left font-medium text-zinc-800 px-4 py-2.5"
                                        @click="expanded = !expanded"
                                        :aria-expanded="expanded"
                                        aria-controls="{{ $textId }}"
                                >
                                    <span>{{ $faq['question'] }}</span>
                                    <svg class="fill-zinc-400 shrink-0 ml-8" width="12" height="12" xmlns="http://www.w3.org/2000/svg">
                                        <rect y="5" width="12" height="2" rx="1"
                                              class="transform origin-center transition duration-200 ease-out"
                                              :class="{'rotate-180!': expanded}"/>
                                        <rect y="5" width="12" height="2" rx="1"
                                              class="transform origin-center rotate-90 transition duration-200 ease-out"
                                              :class="{'rotate-180!': expanded}"/>
                                    </svg>
                                </button>
                            </h2>
                            <div
                                    id="{{ $textId }}"
                                    role="region"
                                    aria-labelledby="{{ $titleId }}"
                                    class="grid text-sm text-zinc-500 overflow-hidden transition-all duration-300 ease-in-out"
                                    :class="expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                            >
                                <div class="overflow-hidden">
                                    <p class="px-4 pb-3">
                                        {{ $faq['answer'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
