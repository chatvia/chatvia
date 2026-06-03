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
                    'messages' => '4,000 messages/credits per month',
                    'agents' => '3 AI agents',
                    'pages' => '1000 pages (PDF, Word, Text)',
                    'links' => '1,000 website links',
                    'minutes' => '300 minutes audio/video',
                    'teams' => '1 team',
                    'members' => '3 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '90 days of chat history',
                ],
                'yearly' => [
                    'messages' => '50,000 messages/credits per year',
                    'agents' => '3 AI agents',
                    'pages' => '1000 pages (PDF, Word, Text)',
                    'links' => '1,000 website links',
                    'minutes' => '300 minutes audio/video',
                    'teams' => '1 team',
                    'members' => '3 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '90 days of chat history',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_STARTER_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STARTER_YEARLY_PRICE_ID'),
            'monthly_price' => 4900,
            'yearly_price' => 46800,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 4000,
                ],
                'yearly' => [
                    'messages_limit' => 50000,
                ],
                'fixed' => [
                    'agents_limit' => 3,
                    'pages_limit' => 1000,
                    'minutes_limit' => 300,
                    'links_limit' => 1000,
                    'custom_domains_limit' => 0,
                    'workspace_users_limit' => 3,
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
                    'messages' => '10,000 messages/credits per month',
                    'agents' => '5 AI agents',
                    'pages' => '3,000 pages (PDF, Word, Text)',
                    'links' => '3,000 website links',
                    'minutes' => '1,200 minutes audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'teams' => '3 team',
                    'members' => '10 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '180 days of chat history',
                ],
                'yearly' => [
                    'messages' => '140,000 messages/credits per year',
                    'agents' => '5 AI agents',
                    'pages' => '3,000 pages (PDF, Word, Text)',
                    'links' => '3,000 website links',
                    'minutes' => '1,200 minutes audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'teams' => '3 team',
                    'members' => '10 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '180 days of chat history',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_STANDARD_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STANDARD_YEARLY_PRICE_ID'),
            'monthly_price' => 9900,
            'yearly_price' => 94800,
            'is_popular' => true,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 10000,
                ],
                'yearly' => [
                    'messages_limit' => 140000,
                ],
                'fixed' => [
                    'agents_limit' => 5,
                    'pages_limit' => 3000,
                    'minutes_limit' => 1200,
                    'links_limit' => 3000,
                    'custom_domains_limit' => 0,
                    'workspace_users_limit' => 10,
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
                    'messages' => '40,000 messages/credits per month',
                    'agents' => '20 AI agents',
                    'pages' => '10,000 pages (PDF, Word, Text)',
                    'links' => '10,000 website links',
                    'minutes' => '10,000 minutes audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'tone' => 'Tone analysis',
                    'topics' => 'Topic analysis',
                    'pulse' => 'Conversation Pulse',
                    'handoff' => 'Handoff',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'api' => 'API access',
                    'integrations' => 'Custom integrations',
                    'teams' => '10 team',
                    'members' => '100 members (team members + chatters)',
                    'retention' => '366 days of chat history',
                ],
                'yearly' => [
                    'messages' => '500,000 messages/credits per year',
                    'agents' => '20 AI agents',
                    'pages' => '10,000 pages (PDF, Word, Text)',
                    'links' => '10,000 website links',
                    'minutes' => '10,000 minutes audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'tone' => 'Tone analysis',
                    'topics' => 'Topic analysis',
                    'pulse' => 'Conversation Pulse',
                    'handoff' => 'Handoff',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'api' => 'API access',
                    'integrations' => 'Custom integrations',
                    'teams' => '10 team',
                    'members' => '100 members (team members + chatters)',
                    'retention' => '366 days of chat history',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_PRO_YEARLY_PRICE_ID'),
            'monthly_price' => 34900,
            'yearly_price' => 334800,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 40000,
                ],
                'yearly' => [
                    'messages_limit' => 500000,
                ],
                'fixed' => [
                    'agents_limit' => 20,
                    'pages_limit' => 10000,
                    'minutes_limit' => 10000,
                    'links_limit' => 10000,
                    'custom_domains_limit' => 1,
                    'workspace_users_limit' => 100,
                    'teams_limit' => 10,
                ],
            ],
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
            'unit_messages' => 1000,
            // Auto-recharge reuses the same Stripe price as Extra Message Credits.
            // The trigger differs (automatic vs. manual), the line item does not.
            'stripe_price_id' => env('STRIPE_EXTRA_1000_MESSAGES_PRICE_ID'),
        ],
        'extra_message_credits' => [
            'name' => 'Extra Message Credits',
            'description' => 'Purchase additional message credits to extend your monthly allowance.',
            'price_cents' => 900,
            'price_formatted' => '$9',
            'billing_unit' => 'per 1,000 credits/month',
            'icon' => 'message-circle',
            'unit_messages' => 1000,
            'stripe_price_id' => env('STRIPE_EXTRA_1000_MESSAGES_PRICE_ID'),
        ],
        'extra_agents' => [
            'name' => 'Extra AI Agents',
            'description' => 'Add more AI agents beyond your plan limit.',
            'price_cents' => 500,
            'price_formatted' => '$5',
            'billing_unit' => 'per agent/month',
            'icon' => 'bot',
            'stripe_price_id' => env('STRIPE_EXTRA_AGENT_PRICE_ID'),
        ],
        'custom_domains' => [
            'name' => 'Custom Domain',
            'description' => 'Use your own custom domain for AI agents.',
            'price_cents' => 2900,
            'price_formatted' => '$29',
            'billing_unit' => 'per domain/month',
            'icon' => 'globe',
            'stripe_price_id' => env('STRIPE_CUSTOM_DOMAIN_PRICE_ID'),
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
        'days' => env('STRIPE_TRIAL_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */
    'currency' => env('CASHIER_CURRENCY', 'eur'),
    'currency_symbol' => env('BILLING_CURRENCY_SYMBOL', '€'),

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'yearly_savings_percentage' => 20,
        'yearly_savings_text' => 'Save 20%',
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
