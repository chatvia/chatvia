<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Packages Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines all available subscription packages for your
    | application. Each package consists of two main components:
    |
    | 1. FEATURES: User-facing descriptions for marketing and UI display
    | 2. QUOTA_LIMITS: Usage tracking limits for billing periods
    |
    | NOTE: Capabilities are defined in BillingPackageCapabilitiesData DTO.
    | Use BillingPackageCapabilitiesData::forPlan('starter') at runtime.
    |
    */

    'discount' => [
        'percentage' => 0,
        'ends_at' => null,
    ],

    'packages' => [
        /*
        |----------------------------------------------------------------------
        | Starter Plan - $30/month
        |----------------------------------------------------------------------
        */
        'starter' => [
            'name' => 'Starter',
            'description' => 'For individuals and small projects.',
            'features' => [
                'monthly' => [
                    'models' => 'Access to all models',
                    'messages' => '2,000 messages per month',
                    'agents' => '3 AI agents',
                    'pages' => '500 pages (PDF, Word, Text)',
                    'links' => '1,000 website links',
                    'minutes' => '300 minutes audio/video',
                    'teams' => '1 team',
                    'members' => '3 team members',
                    'pdf' => 'Basic PDF processing',
                    'transcription' => 'Audio & video transcription',
                    'analytics' => 'Basic analytics (metrics & sources)',
                    'subdomain' => 'Custom subdomain',
                ],
                'yearly' => [
                    'messages' => '25,000 messages per year',
                    'agents' => '3 AI agents',
                    'pages' => '500 pages (PDF, Word, Text)',
                    'links' => '1,000 website links',
                    'minutes' => '300 minutes audio/video',
                    'teams' => '1 team',
                    'members' => '3 team members',
                    'pdf' => 'Basic PDF processing',
                    'transcription' => 'Audio & video transcription',
                    'analytics' => 'Basic analytics (metrics & sources)',
                    'subdomain' => 'Custom subdomain',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_STARTER_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STARTER_YEARLY_PRICE_ID'),
            'monthly_price' => 3000,
            'yearly_price' => 21600,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 2000,
                ],
                'yearly' => [
                    'messages_limit' => 25000,
                ],
                'fixed' => [
                    'agents_limit' => 3,
                    'pages_limit' => 500,
                    'minutes_limit' => 300,
                    'links_limit' => 1000,
                    'custom_domains_limit' => 0,
                    'members_limit' => 3,
                    'teams_limit' => 1,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Standard Plan - $100/month
        |----------------------------------------------------------------------
        */
        'standard' => [
            'name' => 'Standard',
            'description' => 'For growing teams and businesses.',
            'features' => [
                'monthly' => [
                    'messages' => '10,000 messages per month',
                    'agents' => '5 AI agents',
                    'pages' => '1,000 pages (PDF, Word, Text)',
                    'links' => '3,000 website links',
                    'minutes' => '1,200 minutes audio/video',
                    'teams' => '3 teams',
                    'members' => '10 team members',
                    'pdf' => 'Advanced PDF with image understanding',
                    'ocr' => 'OCR for scanned documents',
                    'collaboration' => 'Team collaboration',
                    'sentiment' => 'Sentiment analysis',
                    'analytics' => 'Advanced analytics',
                    'subdomain' => 'Custom subdomain',
                ],
                'yearly' => [
                    'messages' => '60,000 messages per year',
                    'agents' => '5 AI agents',
                    'pages' => '1,000 pages (PDF, Word, Text)',
                    'links' => '3,000 website links',
                    'minutes' => '1,200 minutes audio/video',
                    'teams' => '3 teams',
                    'members' => '10 team members',
                    'pdf' => 'Advanced PDF with image understanding',
                    'ocr' => 'OCR for scanned documents',
                    'collaboration' => 'Team collaboration',
                    'sentiment' => 'Sentiment analysis',
                    'analytics' => 'Advanced analytics',
                    'subdomain' => 'Custom subdomain',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_STANDARD_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STANDARD_YEARLY_PRICE_ID'),
            'monthly_price' => 10000,
            'yearly_price' => 72000,
            'is_popular' => true,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 5000,
                ],
                'yearly' => [
                    'messages_limit' => 60000,
                ],
                'fixed' => [
                    'agents_limit' => 5,
                    'pages_limit' => 1000,
                    'minutes_limit' => 1200,
                    'links_limit' => 3000,
                    'custom_domains_limit' => 0,
                    'members_limit' => 10,
                    'teams_limit' => 3,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Pro Plan - $400/month
        |----------------------------------------------------------------------
        */
        'pro' => [
            'name' => 'Pro',
            'description' => 'For organizations that need it all.',
            'features' => [
                'monthly' => [
                    'messages' => '50,000 messages per month',
                    'agents' => '20 AI agents',
                    'pages' => '5,000 pages (PDF, Word, Text)',
                    'links' => '10,000 website links',
                    'minutes' => '6,000 minutes audio/video',
                    'teams' => '10 teams',
                    'members' => '100 team members',
                    'pdf' => 'Advanced PDF with image understanding',
                    'ocr' => 'OCR for scanned documents',
                    'transcription' => 'Audio & video transcription',
                    'analytics' => 'Full analytics (topics, sentiment, tone)',
                    'domains' => 'Custom domains',
                    'api' => 'API access',
                    'integrations' => 'Custom integrations',
                    'collaboration' => 'Team collaboration',
                ],
                'yearly' => [
                    'messages' => '300,000 messages per year',
                    'agents' => '20 AI agents',
                    'pages' => '5,000 pages (PDF, Word, Text)',
                    'links' => '10,000 website links',
                    'minutes' => '6,000 minutes audio/video',
                    'teams' => '10 teams',
                    'members' => '100 team members',
                    'pdf' => 'Advanced PDF with image understanding',
                    'ocr' => 'OCR for scanned documents',
                    'transcription' => 'Audio & video transcription',
                    'analytics' => 'Full analytics (topics, sentiment, tone)',
                    'domains' => 'Custom domains',
                    'api' => 'API access',
                    'integrations' => 'Custom integrations',
                    'collaboration' => 'Team collaboration',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_PRO_YEARLY_PRICE_ID'),
            'monthly_price' => 40000,
            'yearly_price' => 288000,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 25000,
                ],
                'yearly' => [
                    'messages_limit' => 300000,
                ],
                'fixed' => [
                    'agents_limit' => 20,
                    'pages_limit' => 5000,
                    'minutes_limit' => 6000,
                    'links_limit' => 10000,
                    'custom_domains_limit' => 1,
                    'members_limit' => 100,
                    'teams_limit' => 10,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Comparison Features Configuration
    |--------------------------------------------------------------------------
    |
    | Defines features displayed in the pricing comparison table. Each feature
    | maps to a capability key from BillingPackageCapabilitiesData DTO.
    |
    */
    'comparison_features' => [
        'advanced_pdf' => [
            'name' => 'Advanced PDF Processing',
            'description' => 'Extract and understand complex PDFs with tables, images, and layouts',
            'capability_key' => 'advanced_pdf',
        ],
        'ocr' => [
            'name' => 'OCR Support',
            'description' => 'Extract text from images and scanned documents',
            'capability_key' => 'ocr',
        ],
        'audio' => [
            'name' => 'Audio Processing',
            'description' => 'Transcribe and process audio files',
            'capability_key' => 'audio',
        ],
        'video' => [
            'name' => 'Video Processing',
            'description' => 'Transcribe and process video files',
            'capability_key' => 'video',
        ],
        'team_collaboration' => [
            'name' => 'Team Collaboration',
            'description' => 'Invite team members to collaborate on AI agents',
            'capability_key' => 'team_collaboration',
        ],
        'api_access' => [
            'name' => 'API Access',
            'description' => 'Integrate with your applications using our REST API',
            'capability_key' => 'api_access',
        ],
        'custom_integrations' => [
            'name' => 'Custom Integrations',
            'description' => 'Build custom integrations with third-party services',
            'capability_key' => 'custom_integrations',
        ],
        'custom_domains' => [
            'name' => 'Custom Domains',
            'description' => 'Use your own custom domains for your AI agents',
            'capability_key' => 'custom_domains',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Add-ons
    |--------------------------------------------------------------------------
    */
    'addons' => [
        'auto_recharge_credits' => [
            'name' => 'Auto Recharge Credits',
            'description' => 'Automatically add credits when your balance falls below a threshold.',
            'price_cents' => 1000,
            'price_formatted' => '$10',
            'billing_unit' => 'per 1,000 credits',
            'icon' => 'credit-card',
        ],
        'extra_message_credits' => [
            'name' => 'Extra Message Credits',
            'description' => 'Purchase additional message credits to extend your monthly allowance.',
            'price_cents' => 900,
            'price_formatted' => '$9',
            'billing_unit' => 'per 1,000 credits/month',
            'icon' => 'message-circle',
            'stripe_price_id' => env('STRIPE_EXTRA_1000_MESSAGES_PRICE_ID'),
        ],
        'extra_agents' => [
            'name' => 'Extra AI Agents',
            'description' => 'Add more AI agents beyond your plan limit.',
            'price_cents' => 500,
            'price_formatted' => '$5',
            'billing_unit' => 'per agent/month',
            'icon' => 'bot',
        ],
        'custom_domains' => [
            'name' => 'Custom Domain',
            'description' => 'Use your own custom domain for AI agents.',
            'price_cents' => 2900,
            'price_formatted' => '$29',
            'billing_unit' => 'per domain/month',
            'icon' => 'globe',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Package
    |--------------------------------------------------------------------------
    */
    'default_package' => 'starter',

    /*
    |--------------------------------------------------------------------------
    | Trial Settings
    |--------------------------------------------------------------------------
    */
    'trial' => [
        'days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */
    'currency' => env('CASHIER_CURRENCY', 'usd'),
    'currency_symbol' => env('BILLING_CURRENCY_SYMBOL', '$'),

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'yearly_savings_percentage' => 40,
        'yearly_savings_text' => 'Save 40%',
        'most_popular_text' => 'Most Popular',
        'current_plan_text' => 'Current Plan',
        'popular_badge_text' => 'Most Popular',
        'money_back_guarantee_days' => 30,
        'yearly_upfront_text' => 'All credits available immediately',
        'yearly_benefit_text' => 'Get your full year allocation upfront',
    ],

    /*
    |--------------------------------------------------------------------------
    | Universal Features
    |--------------------------------------------------------------------------
    */
    'common_features' => [
        'models' => 'Access to all AI models',
        'security' => 'Secure & encrypted platform',
        'updates' => 'Regular updates & improvements',
        'cancel' => 'Cancel anytime',
        'fees' => 'No setup fees',
        'guarantee' => '30-day money-back guarantee',
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Icons
    |--------------------------------------------------------------------------
    */
    'package_icons' => [
        'starter' => 'zap',
        'standard' => 'star',
        'pro' => 'crown',
    ],
];
