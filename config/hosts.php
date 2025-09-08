<?php

return [
    'chatvia.test' => [
        'alias' => 'chatvia.ai',
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
            'keywords' => ['Chatvia.ai', 'Chatbot', 'Chat widget', 'Chat platform'],
            'description' => 'Chatvia.ai is a powerful chatbot platform that helps you create engaging and personalized interactions with your customers.',
            'navigation' => [
                ['label' => 'Home', 'route' => 'home'],
            ],
            'hero' => [
                'top' => 'From PDFs, websites, and knowledge bases',
                'headline' => 'Instantly create GPTs tailored to your business',
                'subheadlines' => [
                    'ChatVia instantly turns your data into intelligent chatbots that answer your questions 24/7',
                ],
                'button' => [
                    'label' => 'Get started for free',
                    'route' => 'register',
                ],
                'text_below_button' => 'No credit card required',
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
                            'img' => 'images/chatvia/001-create-agent.png',
                            'title' => 'Knowledge base and support widgets, all in one',
                            'description' => 'Build intelligent chatbots with our intuitive agent creation tool. Upload your knowledge base, configure responses, and launch your AI assistant in minutes.',
                        ],
                        [
                            'img' => 'images/chatvia/002-control-panel.png',
                            'title' => 'Comprehensive Control Panel',
                            'description' => 'Monitor performance, manage multiple agents, and access detailed analytics from a single dashboard. Get real-time insights into your chatbot interactions.',
                        ],
                        [
                            'img' => 'images/chatvia/003-leads.png',
                            'title' => 'Lead Management System',
                            'description' => 'Capture, track, and nurture leads automatically. Our intelligent system identifies potential customers and helps you convert conversations into sales.',
                        ],
                        [
                            'img' => 'images/chatvia/004-customization.png',
                            'title' => 'Fully Customizable Experience',
                            'description' => 'Personalize your chatbot appearance, behavior, and responses to match your brand. Choose from themes, customize colors, and create unique user experiences.',
                        ],
                        [
                            'img' => 'images/chatvia/005-chat-logs.png',
                            'title' => 'Detailed Chat Analytics',
                            'description' => 'Access comprehensive chat logs and conversation history. Analyze user interactions, identify common questions, and continuously improve your chatbot performance.',
                        ],
                    ],
                ],
                'slide2' => [
                    'title' => 'Discover our unique properties',
                    'subheadline' => '',
                    'slides' => [
                        //                        ['img' => 'images/chatvia/001-create-agent.png', 'title' => 'Knowledage base and support widgets, all in one'],
                        //                        ['img' => 'images/chatvia/002-control-panel.png', 'title' => 'Knowledage base and support widgets, all in one'],
                        //                        ['img' => 'images/chatvia/003-leads.png', 'title' => 'Leads'],
                        //                        ['img' => 'images/chatvia/004-customization.png', 'title' => 'Fully customizable'],
                        //                        ['img' => 'images/chatvia/005-chat-logs.png', 'title' => 'Fully customizable'],
                    ],
                ],
                'steps' => [
                    'title' => 'You are three steps way from transforming your business',
                    'steps' => [
                        ['title' => 'Create', 'description' => 'Just click on the Create Agent button to get started'],
                        ['title' => 'Train', 'description' => ''],
                        ['title' => 'Deploy', 'description' => ''],
                    ],
                ],
            ],

            'cta' => [
                'headline' => 'Say goodbye to scattered files and endless <span class="text-emerald-500">searching</span>.',
                'description' => 'Create custom GPTs in minutes — powered by your data.',
                'button' => [
                    'label' => 'Get started for free',
                    'route' => 'register',
                ],
            ],

            'social' => [

            ],
        ],
    ],
];
