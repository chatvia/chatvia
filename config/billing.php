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
    | NOTE: `monthly_price` / `yearly_price` (in cents) are DISPLAY values only.
    | The amount actually charged comes from the Stripe Price objects behind the
    | STRIPE_*_PRICE_ID env vars — changing the numbers here without creating
    | matching Stripe prices only changes what the UI shows, not what Stripe bills.
    |
    | NOTE: The package keys ('starter', 'standard', ...) are stored verbatim in
    | Stripe subscription metadata (`package` / `to_package`) by the checkout and
    | plan-change flows, and renewal webhooks look the key back up here. Renaming
    | a key therefore requires updating the metadata on existing Stripe
    | subscriptions too — otherwise GrantSubscriptionAction logs a warning and
    | silently skips the quota grant on their next renewal.
    |
    */

    'discount' => [
        'percentage' => 0,
        'ends_at' => null,
    ],

    'packages' => [
        /*
        |----------------------------------------------------------------------
        | Starter Plan - €99/month
        |----------------------------------------------------------------------
        */
        'starter' => [
            'name' => 'Starter',
            'description' => 'For small businesses getting started.',
            'features' => [
                'monthly' => [
                    'messages' => '6,000 messages per month',
                    'agents' => '5 AI agents',
                    'pages' => '2,000 pages (PDF, Word, Text)',
                    'links' => '2,000 website links',
                    'minutes' => '500 minutes audio/video',
                    'teams' => '1 team',
                    'members' => '5 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '90 days of chat history',
                ],
                'yearly' => [
                    'messages' => '78,000 messages per year',
                    'agents' => '5 AI agents',
                    'pages' => '2,000 pages (PDF, Word, Text)',
                    'links' => '2,000 website links',
                    'minutes' => '500 minutes audio/video',
                    'teams' => '1 team',
                    'members' => '5 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '90 days of chat history',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_STARTER_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STARTER_YEARLY_PRICE_ID'),
            'monthly_price' => 9900,
            'yearly_price' => 94800,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 6000,
                ],
                'yearly' => [
                    'messages_limit' => 78000,
                ],
                'fixed' => [
                    'agents_limit' => 5,
                    'pages_limit' => 2000,
                    'minutes_limit' => 500,
                    'links_limit' => 2000,
                    'custom_domains_limit' => 0,
                    'workspace_users_limit' => 5,
                    'teams_limit' => 1,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Standard Plan - €199/month
        |----------------------------------------------------------------------
        */
        'standard' => [
            'name' => 'Standard',
            'description' => 'For growing teams and businesses.',
            'features' => [
                'monthly' => [
                    'messages' => '15,000 messages per month',
                    'agents' => '10 AI agents',
                    'pages' => '5,000 pages (PDF, Word, Text)',
                    'links' => '5,000 website links',
                    'minutes' => '2,000 minutes audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'handoff' => 'Handoff',
                    'api' => 'API access',
                    'teams' => '5 team',
                    'members' => '20 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '180 days of chat history',
                ],
                'yearly' => [
                    'messages' => '192,000 messages per year',
                    'agents' => '10 AI agents',
                    'pages' => '5,000 pages (PDF, Word, Text)',
                    'links' => '5,000 website links',
                    'minutes' => '2,000 minutes audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'handoff' => 'Handoff',
                    'api' => 'API access',
                    'teams' => '5 team',
                    'members' => '20 members (team members + chatters)',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'retention' => '180 days of chat history',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_STANDARD_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STANDARD_YEARLY_PRICE_ID'),
            'monthly_price' => 19900,
            'yearly_price' => 190800,
            'is_popular' => true,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 15000,
                ],
                'yearly' => [
                    'messages_limit' => 192000,
                ],
                'fixed' => [
                    'agents_limit' => 10,
                    'pages_limit' => 5000,
                    'minutes_limit' => 2000,
                    'links_limit' => 5000,
                    'custom_domains_limit' => 0,
                    'workspace_users_limit' => 20,
                    'teams_limit' => 5,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Pro Plan - €499/month
        |----------------------------------------------------------------------
        */
        'pro' => [
            'name' => 'Pro',
            'description' => 'For organizations that need it all.',
            'features' => [
                'monthly' => [
                    'messages' => '50,000 messages per month',
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
                    'branding' => 'White-label (remove Chatvia branding)',
                    'teams' => '10 team',
                    'members' => '100 members (team members + chatters)',
                    'retention' => '366 days of chat history',
                ],
                'yearly' => [
                    'messages' => '648,000 messages per year',
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
                    'branding' => 'White-label (remove Chatvia branding)',
                    'teams' => '10 team',
                    'members' => '100 members (team members + chatters)',
                    'retention' => '366 days of chat history',
                ],
            ],
            'stripe_monthly_price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_PRO_YEARLY_PRICE_ID'),
            'monthly_price' => 49900,
            'yearly_price' => 478800,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 50000,
                ],
                'yearly' => [
                    'messages_limit' => 648000,
                ],
                'fixed' => [
                    'agents_limit' => 20,
                    'pages_limit' => 10000,
                    'minutes_limit' => 10000,
                    'links_limit' => 10000,
                    'custom_domains_limit' => 2,
                    'workspace_users_limit' => 100,
                    'teams_limit' => 10,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Enterprise Plan - Contact sales
        |----------------------------------------------------------------------
        | Not purchasable through Stripe Checkout. `contact_sales` tells the
        | pricing UIs to show "Custom" instead of a price and route the CTA to
        | the demo/contact form. Prices are 0 and the Stripe price IDs are null,
        | so this package never matches a Stripe webhook in getPackageByPriceId().
        | The quota_limits below are display-only — Enterprise accounts are
        | provisioned manually, never via the self-serve subscription flow.
        */
        'enterprise' => [
            'name' => 'Enterprise',
            'description' => 'For organizations with security, compliance, and scale needs.',
            'contact_sales' => true,
            'features' => [
                'monthly' => [
                    'messages' => 'Custom message volume',
                    'agents' => 'Unlimited AI agents',
                    'pages' => 'Custom knowledge base',
                    'links' => 'Custom website links',
                    'minutes' => 'Custom audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'tone' => 'Tone analysis',
                    'topics' => 'Topic analysis',
                    'pulse' => 'Conversation Pulse',
                    'handoff' => 'Handoff',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'api' => 'API access',
                    'integrations' => 'Custom integrations',
                    'branding' => 'White-label (remove Chatvia branding)',
                    'residency' => 'EU data residency',
                    'sso' => 'SSO / SAML',
                    'sla' => 'Uptime SLA',
                    'support' => 'Dedicated support',
                    'dpa' => 'Custom contract & DPA',
                    'teams' => 'Unlimited teams',
                    'members' => 'Unlimited members',
                    'retention' => 'Custom data retention',
                ],
                'yearly' => [
                    'messages' => 'Custom message volume',
                    'agents' => 'Unlimited AI agents',
                    'pages' => 'Custom knowledge base',
                    'links' => 'Custom website links',
                    'minutes' => 'Custom audio/video',
                    'sentiment' => 'Sentiment analysis',
                    'tone' => 'Tone analysis',
                    'topics' => 'Topic analysis',
                    'pulse' => 'Conversation Pulse',
                    'handoff' => 'Handoff',
                    'mcp' => 'MCP',
                    'function_calling' => 'Function Calling',
                    'api' => 'API access',
                    'integrations' => 'Custom integrations',
                    'branding' => 'White-label (remove Chatvia branding)',
                    'residency' => 'EU data residency',
                    'sso' => 'SSO / SAML',
                    'sla' => 'Uptime SLA',
                    'support' => 'Dedicated support',
                    'dpa' => 'Custom contract & DPA',
                    'teams' => 'Unlimited teams',
                    'members' => 'Unlimited members',
                    'retention' => 'Custom data retention',
                ],
            ],
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 200000,
                ],
                'yearly' => [
                    'messages_limit' => 2400000,
                ],
                'fixed' => [
                    'agents_limit' => 100,
                    'pages_limit' => 100000,
                    'minutes_limit' => 100000,
                    'links_limit' => 100000,
                    'custom_domains_limit' => 10,
                    'workspace_users_limit' => 1000,
                    'teams_limit' => 100,
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
            'price_formatted' => '€10',
            'billing_unit' => 'per 1,000 credits',
            'icon' => 'credit-card',
            'unit_messages' => 1000,
            // Auto-recharge reuses the same Stripe price as Extra Message Credits.
            // The trigger differs (automatic vs. manual), the line item does not.
            'stripe_price_id' => env('STRIPE_EXTRA_1000_MESSAGES_PRICE_ID'),
            // Max off-session auto-recharges per rolling 24h. The hourly debounce
            // alone permits ~24 card charges/day; this caps a runaway spend spike
            // driven by abuse (e.g. anonymous public-widget traffic).
            'max_per_day' => (int) env('AUTO_RECHARGE_MAX_PER_DAY', 3),
        ],
        'extra_message_credits' => [
            'name' => 'Extra Message Credits',
            'description' => 'Purchase additional message credits to extend your monthly allowance.',
            'price_cents' => 900,
            'price_formatted' => '€9',
            'billing_unit' => 'per 1,000 credits/month',
            'icon' => 'message-circle',
            'unit_messages' => 1000,
            'stripe_price_id' => env('STRIPE_EXTRA_1000_MESSAGES_PRICE_ID'),
        ],
        'extra_agents' => [
            'name' => 'Extra AI Agents',
            'description' => 'Add more AI agents beyond your plan limit.',
            'price_cents' => 500,
            'price_formatted' => '€5',
            'billing_unit' => 'per agent/month',
            'icon' => 'bot',
            'stripe_price_id' => env('STRIPE_EXTRA_AGENT_PRICE_ID'),
        ],
        'custom_domains' => [
            'name' => 'Custom Domain',
            'description' => 'Use your own custom domain for AI agents.',
            'price_cents' => 2900,
            'price_formatted' => '€29',
            'billing_unit' => 'per domain/month',
            'icon' => 'globe',
            'stripe_price_id' => env('STRIPE_CUSTOM_DOMAIN_PRICE_ID'),
        ],
        'remove_branding' => [
            'name' => 'Remove Chatvia Branding',
            'description' => 'Hide the "Powered by Chatvia" badge across your widget and hosted chat.',
            'price_cents' => 1900,
            'price_formatted' => '€19',
            'billing_unit' => 'per month',
            'icon' => 'sparkles',
            'stripe_price_id' => env('STRIPE_REMOVE_BRANDING_PRICE_ID'),
        ],
        'members' => [
            'name' => 'Members',
            'description' => 'Add more team members to your workspace.',
            'price_cents' => 900,
            'price_formatted' => '€9',
            'billing_unit' => 'per member/month',
            'icon' => 'users',
            'stripe_price_id' => env('STRIPE_MEMBERS_PRICE_ID'),
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
        'enterprise' => 'building',
    ],
];
