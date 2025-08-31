<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Packages Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines all available subscription packages for your
    | application. Each package consists of three main components:
    |
    | 1. FEATURES: User-facing descriptions for marketing and UI display
    | 2. CAPABILITIES: Backend flags for code logic and feature checking
    | 3. QUOTA_LIMITS: Usage tracking limits for billing periods
    |
    | Example usage in code:
    | - UI: get_plan_features('starter') // Returns marketing features
    | - Logic: user_has_capability('ocr') // Checks backend capability
    | - Quotas: $user->quota->pages_limit // Gets usage limits
    |
    */

    'discount' => [
        'percentage' => 0,
        'ends_at' => null,
    ],

    'packages' => [
        /*
        |----------------------------------------------------------------------
        | Free Plan
        |----------------------------------------------------------------------
        |
        | Entry-level plan for users to try the platform. No payment required.
        | Limited features and capabilities to encourage upgrades.
        |
        */
        'free' => [
            'name' => 'Free',
            'description' => 'Perfect for trying out our AI agents',

            /*
            | UI FEATURES: What users see on pricing pages and marketing materials.
            | Separate features for monthly vs yearly to show different benefits.
            */
            'features' => [
                'monthly' => [
                    'Access to fast models',
                    '100 messages/month',
                    '50 pages',
                    '50 links',
                    '1 AI agent',
                    '1 Member',
                    'Basic PDF',
                    'Random subdomain',
                ],
                'yearly' => [
                    'Access to fast models',
                    '100 messages/month',
                    '50 pages',
                    '50 links',
                    '1 AI agent',
                    '1 Member',
                    'Basic PDF',
                    'Random subdomain',
                ],
            ],

            /*
            | CAPABILITIES: Backend feature flags that your code should check.
            | Use these in your application logic with user_has_capability('key').
            |
            | Boolean values: true/false for simple on/off features
            | String values: Support levels like 'email', 'priority', 'dedicated'
            | Numeric values: Limits like storage_gb, max_agents, etc.
            | Array values: Lists of allowed items like models, file types
            */
            'capabilities' => [
                'custom_sub_domain' => false,
                'custom_domains' => false,
                'advanced_pdf' => false,
                'ocr' => false,
                'audio' => false,
                'video' => false,
                'api_access' => false,
                'custom_integrations' => false,
                'team_collaboration' => true,
                'priority_support' => false,
            ],
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'is_popular' => false,

            /*
            | QUOTA LIMITS: Usage tracking limits for this billing plan.
            | These are enforced by the QuotaService and reset each billing period.
            | Separate limits for monthly vs yearly subscriptions to provide
            | better value for annual commitments.
            */
            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 100,
                ],
                'yearly' => [
                    'messages_limit' => 100,
                ],
                'fixed' => [
                    'agents_limit' => 1,
                    'pages_limit' => 50,
                    'minutes_limit' => 30,
                    'links_limit' => 50,
                    'custom_domains_limit' => 0,
                    'members_limit' => 1,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Starter Plan
        |----------------------------------------------------------------------
        |
        | Entry-level paid plan with essential features for individuals and
        | small projects. Includes basic AI capabilities and file processing.
        |
        */
        'starter' => [
            'name' => 'Starter',
            'description' => 'Ideal for individuals and small projects',
            'features' => [
                'monthly' => [
                    'Access to all models',
                    '1,000 messages/month',
                    '500 pages',
                    '1,000 links',
                    '3 AI agents',
                    '2 Members',
                    'Advanced PDF',
                    'Custom subdomain',
                ],
                'yearly' => [
                    'Access to all models',
                    '13,000 messages upfront',
                    '500 pages',
                    '1,000 links',
                    '3 AI agents',
                    '2 Members',
                    'Advanced PDF',
                    'Custom subdomain',
                ],
            ],

            'capabilities' => [
                'custom_sub_domain' => true,
                'custom_domains' => false,
                'advanced_pdf' => true,
                'ocr' => true,
                'audio' => false,
                'video' => false,
                'api_access' => false,
                'custom_integrations' => false,
                'team_collaboration' => true,
                'priority_support' => false,
            ],
            'stripe_monthly_price_id' => env('STRIPE_STARTER_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STARTER_YEARLY_PRICE_ID'),
            'monthly_price' => 1900,
            'yearly_price' => 9000,
            'is_popular' => false,

            'quota_limits' => [
                'monthly' => [
                    'messages_limit' => 1000,
                ],
                'yearly' => [
                    'messages_limit' => 13000,
                ],
                'fixed' => [
                    'agents_limit' => 3,
                    'pages_limit' => 500,
                    'minutes_limit' => 300,
                    'links_limit' => 1000,
                    'custom_domains_limit' => 0,
                    'members_limit' => 2,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Standard Plan
        |----------------------------------------------------------------------
        |
        | Mid-tier plan for growing businesses. Includes team collaboration,
        | API access, and advanced analytics. Most popular choice.
        |
        */
        'standard' => [
            'name' => 'Standard',
            'description' => 'Perfect for growing businesses and teams',
            'features' => [
                'monthly' => [
                    '5,000 messages/month',
                    '1,000 pages',
                    '3,000 links',
                    '5 AI agents',
                    '5 Members',
                ],
                'yearly' => [
                    '60,000 messages upfront',
                    '1,000 pages',
                    '3,000 links',
                    '5 AI agents',
                    '5 Members',
                ],
            ],

            'capabilities' => [
                'custom_sub_domain' => true,
                'custom_domains' => false,
                'advanced_pdf' => true,
                'ocr' => true,
                'audio' => false,
                'video' => false,
                'api_access' => false,
                'custom_integrations' => false,
                'team_collaboration' => true,
                'priority_support' => false,
            ],
            'stripe_monthly_price_id' => env('STRIPE_STANDARD_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STANDARD_YEARLY_PRICE_ID'),
            'monthly_price' => 5000,
            'yearly_price' => 30000,
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
                    'members_limit' => 5,
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Pro Plan
        |----------------------------------------------------------------------
        |
        | Enterprise-grade solution with premium features like white labeling,
        | custom domains, audio/video processing, and dedicated support.
        |
        */
        'pro' => [
            'name' => 'Pro',
            'description' => 'Enterprise-grade solution for large organizations',
            'features' => [
                'monthly' => [
                    '25,000 messages/month',
                    '5,000 pages',
                    '10,000 links',
                    '20 AI agents',
                    '100 Members',
                    'Free custom integrations',
                    'Priority support',
                ],
                'yearly' => [
                    '300,000 messages upfront',
                    '5,000 pages',
                    '10,000 links',
                    '20 AI agents',
                    '100 Members',
                    'Free custom integrations',
                    'Priority support',
                ],
            ],

            'capabilities' => [
                'custom_sub_domain' => true,
                'custom_domains' => true,
                'advanced_pdf' => true,
                'ocr' => true,
                'audio' => true,
                'video' => true,
                'api_access' => true,
                'custom_integrations' => true,
                'team_collaboration' => true,
                'priority_support' => true,
            ],
            'stripe_monthly_price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_PRO_YEARLY_PRICE_ID'),
            'monthly_price' => 25000,
            'yearly_price' => 150000,
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
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Comparison Features Configuration
    |--------------------------------------------------------------------------
    |
    | Defines features displayed in the pricing comparison table on your
    | billing pages. Each feature maps a user-friendly display to an actual
    | capability key that exists in the packages above.
    |
    | Structure:
    | - 'name': Display name shown to users
    | - 'description': Tooltip/help text explaining the feature
    | - 'capability_key': Maps to the actual capability in package configs
    |
    | The Vue component uses 'capability_key' to check each package's
    | capabilities and display ✓/✗ or values in the comparison table.
    |
    */
    'comparison_features' => [
        'ai_agents' => [
            'name' => 'Document-Based Agents',
            'description' => 'AI agents trained on your documents, PDFs, and knowledge base',
            'capability_key' => 'max_agents',
        ],
        'monthly_message_credits' => [
            'name' => 'Monthly Messages',
            'description' => 'Total conversations you can have with your AI agents each month',
            'capability_key' => 'monthly_message_credits',
        ],
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
        'audio_video' => [
            'name' => 'Audio & Video Processing',
            'description' => 'Process and transcribe audio and video files',
            'capability_key' => 'audio_video',
        ],
        'api_access' => [
            'name' => 'API Access',
            'description' => 'Integrate with your applications using our REST API',
            'capability_key' => 'api_access',
        ],
        'priority_support' => [
            'name' => 'Customer Support',
            'description' => 'Get help when you need it with dedicated support channels',
            'capability_key' => 'priority_support',
        ],
        'advanced_analytics' => [
            'name' => 'Analytics & Insights',
            'description' => 'Track usage, performance, and conversation metrics in real-time',
            'capability_key' => 'advanced_analytics',
        ],
        'white_label' => [
            'name' => 'White Label Branding',
            'description' => 'Customize the interface with your own branding and remove ours',
            'capability_key' => 'white_label',
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
    |
    | Optional services users can purchase on top of their base subscription.
    | These extend plan capabilities beyond the standard limits.
    |
    | Each add-on includes:
    | - Pricing information (cents and formatted display)
    | - Billing unit description (per month, per agent, etc.)
    | - Icon for UI display
    | - User-friendly name and description
    |
    */
    'addons' => [
        'auto_recharge_credits' => [
            'name' => 'Auto recharge credits',
            'description' => 'When your credits fall below the threshold you set, we\'ll automatically add credits that don\'t expire to your account, ensuring uninterrupted service.',
            'price_cents' => 1000, // $10.00
            'price_formatted' => '$10',
            'billing_unit' => 'per 1000 message credits',
            'icon' => 'credit-card',
        ],
        'extra_message_credits' => [
            'name' => 'Extra message credits',
            'description' => 'Purchase additional message credits to extend your monthly allowance.',
            'price_cents' => 900, // $9.00
            'price_formatted' => '$9',
            'billing_unit' => 'per 1000 credits / month',
            'icon' => 'message-circle',
            'stripe_price_id' => env('STRIPE_EXTRA_1000_MESSAGES_PRICE_ID'),

        ],
        'extra_agents' => [
            'name' => 'Extra agents',
            'description' => 'Add more AI agents beyond your plan limit to handle additional use cases.',
            'price_cents' => 500, // $5.00
            'price_formatted' => '$5',
            'billing_unit' => 'per AI agent / month',
            'icon' => 'bot',
        ],
        'custom_domains' => [
            'name' => 'Custom domains',
            'description' => 'Use your own custom domains for the AI agent\'s.',
            'price_cents' => 2900, // $29.00
            'price_formatted' => '$29',
            'billing_unit' => 'per AI agent / month',
            'icon' => 'globe',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Package
    |--------------------------------------------------------------------------
    |
    | The default package that new users will be assigned to.
    |
    */
    'default_package' => 'free',

    /*
    |--------------------------------------------------------------------------
    | Trial Settings
    |--------------------------------------------------------------------------
    |
    | Configure trial periods for packages that support trials.
    |
    */
    'trial' => [
        'days' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The currency to use for billing. This should match your Stripe account
    | currency settings.
    |
    */
    'currency' => env('CASHIER_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Currency Symbol
    |--------------------------------------------------------------------------
    |
    | The currency symbol to display in the UI.
    |
    */
    'currency_symbol' => env('BILLING_CURRENCY_SYMBOL', '$'),

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Customizable text and settings for the billing interface. These values
    | are passed to Vue components for consistent messaging across the app.
    |
    | Used for button text, badges, savings calculations, and other UI elements
    | that may need to be adjusted without code changes.
    |
    */
    'ui' => [
        'yearly_savings_percentage' => 50,
        'yearly_savings_text' => 'Save 50%',
        'most_popular_text' => 'Most Popular',
        'current_plan_text' => 'Current Plan',
        'popular_badge_text' => 'Most Popular',

        'money_back_guarantee_days' => 30,
        'yearly_upfront_text' => 'All credits available immediately',
        'yearly_benefit_text' => 'Get your full year allocation upfront + bonus credits',
    ],

    /*
    |--------------------------------------------------------------------------
    | Universal Features
    |--------------------------------------------------------------------------
    |
    | Features that are included with ALL plans, including free. These appear
    | in a separate "What's included with all plans" section on pricing pages.
    |
    | Typically includes basic platform benefits like security, reliability,
    | and customer support that every user receives.
    |
    */
    'common_features' => [
        'Secure & reliable platform',
        'Regular updates & improvements',
        'Cancel anytime',
        'No setup fees',
        '30-day money-back guarantee',
        'Expert customer support',
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Icons
    |--------------------------------------------------------------------------
    |
    | Icon mappings for each package displayed in the UI. Icons help users
    | quickly identify and differentiate between plans.
    |
    | Supported icons: gift, zap, star, crown, building
    | These map to Lucide icons in the Vue components.
    |
    */
    'package_icons' => [
        'free' => 'gift',
        'starter' => 'zap',
        'standard' => 'star',
        'pro' => 'crown',
        'enterprise' => 'building',
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrated Quota System
    |--------------------------------------------------------------------------
    |
    | Quota management system that works seamlessly with billing plans.
    | The QuotaService automatically uses plan-specific limits defined in
    | the 'quota_limits' section of each package above.
    |
    | Key Components:
    | - Default limits for new users (typically matches free plan)
    | - Warning thresholds for usage notifications
    | - Cache settings for performance optimization
    | - Billing period configuration for quota resets
    | - Rate limiting to prevent quota abuse
    |
    | The system automatically updates user quotas when plans change and
    | resets usage counters at the start of each billing period.
    |
    */

    'quota' => [
        /*
        |--------------------------------------------------------------------------
        | Quota Warning Thresholds
        |--------------------------------------------------------------------------
        |
        | Define at what percentage of usage to warn users.
        |
        */
        'warning_thresholds' => [
            'warning' => 80,  // Warn at 80% usage
            'critical' => 95, // Critical warning at 95% usage
        ],

        /*
        |--------------------------------------------------------------------------
        | Cache Settings
        |--------------------------------------------------------------------------
        |
        | Configure caching behavior for quota data.
        |
        */
        'cache' => [
            'ttl' => env('QUOTA_CACHE_TTL', 300), // 5 minutes default
            'prefix' => 'user_quota:',
            'tags' => ['user_quotas'],
        ],

        /*
        |--------------------------------------------------------------------------
        | Billing Period Settings
        |--------------------------------------------------------------------------
        |
        | Configure how billing periods work.
        |
        */
        'billing_period' => [
            'type' => 'monthly', // monthly, yearly, custom
            'reset_day' => 1, // Day of month to reset (1-31)
            'timezone' => 'UTC',
        ],

        /*
        |--------------------------------------------------------------------------
        | Quota Types
        |--------------------------------------------------------------------------
        |
        | Define all available quota types for validation.
        |
        */
        'quota_types' => [
            'pages',
            'minutes',
            'messages',
        ],

        /*
        |--------------------------------------------------------------------------
        | Rate Limiting
        |--------------------------------------------------------------------------
        |
        | Configure rate limiting for quota operations.
        |
        */
        'rate_limiting' => [
            'enabled' => env('QUOTA_RATE_LIMITING', true),
            'max_increments_per_minute' => 100,
            'max_checks_per_minute' => 500,
        ],
    ],
];
