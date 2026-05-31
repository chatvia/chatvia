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
                    'route' => '#pricing',
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
            'show-why-chatbot' => false,
            'has-widget' => false,
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
        'landing' => [],
    ],
];
