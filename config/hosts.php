<?php

return [
    'localhost' => [
        'alias' => 'chatvia.ai',
    ],

    'chatvia.test' => [
        'alias' => 'chatvia.ai',
    ],

    'www.chatvia.ai' => [
        'alias' => 'chatvia.ai',
    ],

    '0.0.0.0' => [
        'alias' => 'chatvia.ai',
    ],

    'estate.chat' => [
        'user' => [
            'demo' => true,
        ],
        'chat' => [
            'widget' => true,
            'knowledge_base' => true,
        ],
        'pricing' => [
            'show' => false,
        ],
        'landing' => [
            'title' => 'Estate.chat',
            'keywords' => ['Estate.chat', 'Real Estate Chatbot', 'Residential Real Estate', 'Home Buying AI', 'Real Estate Agency'],
            'description' => 'Estate.chat is an AI-powered chatbot platform designed specifically for residential real estate agencies to engage homebuyers and sellers 24/7.',
            'navigation' => [
                //                ['label' => 'Home', 'link' => '/'],
                //                ['label' => 'Features', 'link' => '#features'],
                //                ['label' => 'Pricing', 'link' => '#pricing'],
                //                ['label' => 'Changelog', 'link' => '/changelog'],
            ],
            'hero' => [
                'top' => 'While you\'re busy showing homes and closing deals',
                'headline' => 'Never miss another homebuyer',
                'subheadlines' => [
                    'Let AI captures leads, qualifies buyers, and books showings while you close deals',
                ],
                'button' => [
                    'label' => 'Book Demo',
                    'route' => 'demo',
                ],
                'text_below_button' => 'No credit card required',
            ],
            'bubbles' => [
                ['text' => 'Hi! Are you looking to buy or sell a home?', 'from' => 'bot', 'pos' => 'top-left', 'delay' => 0],
                ['text' => 'I\'m looking for a family home with 4 bedrooms', 'from' => 'user', 'pos' => 'bottom-right', 'delay' => 1200],
                ['text' => 'Great! What\'s your budget and preferred area?', 'from' => 'bot', 'pos' => 'top-right', 'delay' => 2500],
                ['text' => 'I can show you 8 perfect matches right now!', 'from' => 'bot', 'pos' => 'bottom-left', 'delay' => 4000],
            ],
            'llm_marquee' => [
                'enabled' => false,
            ],
            'features' => [
                'short' => [],
                'slide' => [
                    'title' => '',
                    'subheadline' => '',
                    'tabs' => [

                    ],
                ],
                'slide2' => [
                    'title' => 'Built for residential real estate success',
                    'subheadline' => '',
                    'slides' => [],
                ],
                //                'steps' => [
                //                    'title' => 'Transform your residential real estate agency in three steps',
                //                    'steps' => [
                //                        ['title' => 'Upload', 'description' => 'Upload your home listings, neighborhood guides, and market data'],
                //                        ['title' => 'Train', 'description' => 'Train your AI agent to understand your local market and client needs'],
                //                        ['title' => 'Convert', 'description' => 'Start converting website visitors into qualified buyers and sellers'],
                //                    ],
                //                ],
            ],
            'cta' => [
                'headline' => 'Stop losing homebuyers to <span class="text-emerald-500">other agencies</span>.',
                'description' => 'Capture and qualify families looking for their dream home 24/7 with AI-powered assistants.',
                'button' => [
                    'label' => 'Start your free trial',
                    'route' => 'register',
                ],
            ],
            'social' => [],
            'faqs' => [
                [
                    'question' => 'What is Estate.chat?',
                    'answer' => 'Estate.chat is an AI chatbot platform designed specifically for residential real estate agencies. It helps you engage homebuyers and sellers, qualify leads, and provide instant property information 24/7.',
                ],
                [
                    'question' => 'How does it help my residential real estate agency?',
                    'answer' => 'Our AI agents capture leads from your website, qualify buyer and seller intentions, answer questions about homes and neighborhoods, and schedule showings - all while you focus on closing deals.',
                ],
                [
                    'question' => 'Can I upload my home listings and neighborhood data?',
                    'answer' => 'Absolutely! Upload your MLS listings, neighborhood guides, school information, local amenities, and market data. The AI will use this to answer client questions accurately and match buyers with suitable homes.',
                ],
                [
                    'question' => 'Does it work for both buyers and sellers?',
                    'answer' => 'Yes! The AI can qualify homebuyers by understanding their needs, budget, and timeline, while also capturing seller leads by offering market insights and home valuations.',
                ],
                [
                    'question' => 'Can families schedule home showings through the chat?',
                    'answer' => 'Yes! Families can book home showings, open house visits, and consultations directly through the chat. The system integrates with your calendar for automatic scheduling.',
                ],
                [
                    'question' => 'How does it handle neighborhood and school questions?',
                    'answer' => 'Upload neighborhood guides, school ratings, local amenities, and community information. The AI will instantly provide families with the local insights they need to make informed decisions.',
                ],
                [
                    'question' => 'Is it suitable for small residential agencies?',
                    'answer' => 'Absolutely! Estate.chat is perfect for agencies of all sizes. Small agencies especially benefit from having a 24/7 assistant that never misses a lead and can handle multiple inquiries simultaneously.',
                ],
                [
                    'question' => 'Can it provide home valuations?',
                    'answer' => 'Yes! Upload your market data and comparable sales information. The AI can provide preliminary home valuations and market insights to potential sellers, helping you capture listing leads.',
                ],
            ],
            'why-chatbot' => false,
            'target',
        ],
    ],

    'estate.test' => [
        'alias' => 'estate.chat',
    ],

    'chatvia.ai' => [
        'user' => [
            'demo' => false,
        ],
        'chat' => [
            'widget' => true,
            'knowledge_base' => false,
        ],
        'pricing' => [
            'show' => true,
        ],
        'landing' => [
            'title' => 'Chatvia.ai',
            'keywords' => ['Chatvia.ai', 'AI Chatbot', 'Custom GPT', 'ChatGPT Alternative', 'AI Chatbots', 'Document AI', 'Knowledge Base AI'],
            'description' => 'Create custom ChatGPT-like AI chatbots from your data.',
            'navigation' => [
                ['label' => 'Home', 'link' => '/'],
                ['label' => 'Features', 'link' => '/#features'],
                ['label' => 'Pricing', 'link' => '/pricing'],
                ['label' => 'Blog', 'link' => '/blog'],
                ['label' => 'Changelog', 'link' => '/changelog'],
            ],
            'hero' => [
                'top' => '',
                'headline' => 'Turn Your Website Into a High-Converting AI Chatbot',
                'subheadlines' => [
                    'ChatVia instantly helps your customers using AI-generated responses. Get 24/7 support and ultra-high satisfaction rates.',
                ],
                'button' => [
                    'label' => 'Start 7-Day Free Trial',
                    'route' => '#pricing',
                ],
                'text_below_button' => '',
            ],
            'bubbles' => [
                ['text' => 'Hi! How can I help you today?', 'from' => 'bot', 'pos' => 'top-left', 'delay' => 0],
                ['text' => 'Can you help me with product information?', 'from' => 'user', 'pos' => 'bottom-right', 'delay' => 1200],
                ['text' => 'Of course! I have all the details you need.', 'from' => 'bot', 'pos' => 'top-right', 'delay' => 2500],
                ['text' => 'Would you like to schedule a demo?', 'from' => 'bot', 'pos' => 'bottom-left', 'delay' => 4000],
            ],
            'llm_marquee' => [
                'enabled' => true,
                'title' => 'Powered by the world\'s most advanced AI models',
                'subtitle' => 'Choose from leading AI providers to power your chatbots',
                'llms' => [
                    ['name' => 'GPT', 'logo' => 'gpt.svg'],
                    ['name' => 'Claude', 'logo' => 'claude.svg'],
                    ['name' => 'Gemini', 'logo' => 'gemini.svg'],
                    ['name' => 'Llama', 'logo' => 'llama.svg'],
                    ['name' => 'Mistral', 'logo' => 'mistral.svg'],
                    ['name' => 'Cohere', 'logo' => 'cohere.svg'],
                    ['name' => 'DeepSeek', 'logo' => 'deepseek.svg'],
                    ['name' => 'Grok', 'logo' => 'grok.svg'],
                    ['name' => 'Microsoft', 'logo' => 'microsoft.svg'],
                    ['name' => 'Nova', 'logo' => 'nova.svg'],
                    ['name' => 'Qwen', 'logo' => 'qwen.svg'],
                ],
            ],
            'features' => [
                'short' => [
                    //                    [
                    //                        'icon' => 'calendar',
                    //                        'title' => 'Easy to use',
                    //                        'description' => 'No-Code Setup'
                    //                    ],
                ],
                'slide' => [
                    'title' => '',
                    'subheadline' => '',
                    'tabs' => [
                        [
                            'img' => 'images/chatvia/chat-widget.png',
                            'tab_name' => 'Chat Widget',
                            'title' => 'Embeddable Chat Widget',
                            'description' => 'Add a beautiful, customizable chat widget to your website in seconds. Engage visitors instantly with AI-powered conversations that feel natural and helpful.',
                        ],
                        [
                            'img' => 'images/chatvia/gpt.png',
                            'tab_name' => 'ChatGPT-Style',
                            'title' => 'ChatGPT-Style Interface',
                            'description' => 'Provide your users with a familiar ChatGPT-like experience, powered by your own business data. Perfect for knowledge bases, documentation, and customer support.',
                        ],
                        [
                            'img' => 'images/chatvia/training.png',
                            'tab_name' => 'Training',
                            'title' => 'Train on Any Data Source',
                            'description' => 'Upload documents, crawl your website, or connect your knowledge base. Your AI chatbot learns from your content and stays synchronized with your latest information.',
                        ],
                        [
                            'img' => 'images/chatvia/control-panel.png',
                            'tab_name' => 'Control Panel',
                            'title' => 'Comprehensive Control Panel',
                            'description' => 'Manage all your AI agents from one powerful dashboard. Configure settings, monitor performance, and control multiple chatbots with ease.',
                        ],
                        [
                            'img' => 'images/chatvia/stats.png',
                            'tab_name' => 'Analytics',
                            'title' => 'Advanced Analytics & Insights',
                            'description' => 'Track conversations, measure engagement, and understand user behavior with detailed analytics. Make data-driven decisions to improve your chatbot performance.',
                        ],
                        [
                            'img' => 'images/chatvia/chat-log.png',
                            'tab_name' => 'Chat Logs',
                            'title' => 'Complete Conversation History',
                            'description' => 'Review every conversation in detail. Identify common questions, discover knowledge gaps, and continuously refine your chatbot responses for better accuracy.',
                        ],
                        [
                            'img' => 'images/chatvia/pulse.png',
                            'tab_name' => 'Pulse',
                            'title' => 'Tone & Sentiment Analysis',
                            'description' => 'Monitor visitor emotions and sentiment in real-time throughout conversations. Detect frustration, satisfaction, and engagement levels to optimize responses and enhance customer experience.',
                        ],
                        [
                            'img' => 'images/chatvia/leads.png',
                            'tab_name' => 'Leads',
                            'title' => 'Intelligent Lead Capture',
                            'description' => 'Turn conversations into qualified leads automatically. Capture contact information naturally during chat and integrate seamlessly with your CRM.',
                        ],

//                        [
//                            'img' => 'images/chatvia/sentiment-analysis.png',
//                            'tab_name' => 'Sentiment',
//                            'title' => 'Sentiment Analysis',
//                            'description' => 'Analyze the overall sentiment of conversations. Identify trends, spot issues early, and measure customer satisfaction over time.',
//                        ],


//                        [
//                            'img' => 'images/chatvia/models.png',
//                            'tab_name' => 'Models',
//                            'title' => 'Multiple AI Models',
//                            'description' => 'Choose from various open-source AI models including GPT OSS, Llama, DeepSeek, Qwen, Mistral, Gemma, and Holo2. Select the best model for your use case and budget.',
//                        ],
//                        [
//                            'img' => 'images/chatvia/settings.png',
//                            'tab_name' => 'Settings',
//                            'title' => 'Fully Customizable',
//                            'description' => 'Personalize your chatbot appearance, behavior, and responses to match your brand. Choose from themes, customize colors, and create unique experiences.',
//                        ],
//                        [
//                            'img' => 'images/chatvia/qa.png',
//                            'tab_name' => 'Q&A',
//                            'title' => 'Q&A Management',
//                            'description' => 'Create and manage custom Q&A pairs to ensure your chatbot gives accurate answers to frequently asked questions.',
//                        ],
                    ],
                ],
                'slide2' => [
                    'title' => 'Discover our unique properties',
                    'subheadline' => '',
                    'slides' => [
                        //                        ['img' => 'images/chatvia/001-create-agent.png', 'title' => 'Create a custom GPT from your data in minutes', 'description' => 'Upload your documents, connect your knowledge base, or point to your website - and create a custom AI agent that understands your business.'],
                        //                        ['img' => 'images/chatvia/002-control-panel.png', 'title' => 'Knowledage base and support widgets, all in one'],

                    ],
                ],
                'steps' => [
                    'title' => 'No coding platform, deploy instantly',
                    'steps' => [
                        ['title' => 'Create', 'description' => 'Just click on the Create Agent button to get started and choose between a widget and knowledge base'],
                        ['title' => 'Train', 'description' => 'Upload documents, add website content, or connect your knowledge base to train your AI chatbot with your business data'],
                        ['title' => 'Deploy', 'description' => 'Embed the chatbot widget on your website or share a direct link to start answering customer questions instantly 24/7'],
                    ],
                ],
            ],

            'cta' => [
                'headline' => 'Stop losing customers to <span class="text-emerald-500">unanswered questions</span>.',
                'description' => 'Deploy an AI chatbot that knows your business inside out — in minutes, not weeks.',
                'button' => [
                    'label' => 'Start your free trial',
                    'route' => 'register',
                ],
            ],

            'social' => [

            ],

            'faqs' => [
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
                    'question' => 'Can I customize the AI chatbot\'s appearance?',
                    'answer' => 'Yes! You can customize your agent\'s name, personality, and add custom branding, logos, and even use your own domain for a white-label experience.',
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
            ],
            'show-why-chatbot' => true,
            'has-widget' => true,
        ],
    ],
];
