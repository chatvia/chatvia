<?php

return [
    'packages' => [
        // Free
        'free' => [
            'name' => 'Free',
            'description' => 'Perfect for trying out our AI agents',
            'features' => [
                'Access to fast models',
                '100 messages per month',
                'Upload up to 100 pages',
                'Text, PDF, DOC, MD',
                '1 instruction-based AI Agent',
                '1 training-based AI Agent',
                'Random subdomain',
            ],
            'feature_flags' => [
                'monthly_message_credits' => 100,
                'advanced_pdf' => false,
                'ai_agents' => 2,
                'priority_support' => false,
            ],
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'is_popular' => false,
            'limits' => [
                'instruction_agents' => 1,
                'training_agents' => 1,
                'messages_per_month' => 100,
                'models' => [''],
            ],
        ],

        // Package 2: Starter
        'starter' => [
            'name' => 'Starter',
            'description' => 'Ideal for individuals and small projects',
            'features' => [
                'Access to all models',
                '1,000 messages per month',
                'Upload up to 500 pages',
                'PDFs, Word, PowerPoint, Excel, HTML, Images',
                '2 instruction-based AI Agent',
                '2 training-based AI Agent',
                'your-agent-name.chatvia.ai',
            ],
            'feature_flags' => [
                'monthly_message_credits' => 1000,
                'advanced_pdf' => true,
                'ai_agents' => 5,
                'Advanced PDF (tables & images)' => false,
                'api_access' => false,
                'priority_support' => 'Email',
                'custom_integrations' => true,
                'storage_gb' => 2,
                'team_collaboration' => false,
                'advanced_analytics' => 'Basic',
                'white_label' => false,
                'sla_guarantee' => false,
            ],
            'stripe_monthly_price_id' => env('STRIPE_STARTER_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STARTER_YEARLY_PRICE_ID'),
            'monthly_price' => 1900, // $19/month
            'yearly_price' => 19000, // $190/year (2 months free)
            'is_popular' => false,
            'limits' => [
                'agents' => 5,
                'messages_per_month' => 1000,
                'models' => ['gpt-3.5-turbo', 'gpt-4o-mini'],
                'integrations' => 3,
                'storage_gb' => 2,
            ],
        ],

        'standard' => [
            'name' => 'Standard',
            'description' => 'Perfect for growing businesses and teams',
            'features' => [
                '3,000 messages per month',
                'Upload up to 1000 pages',
                '44 more file types',
                '4 instruction-based AI Agent',
                '4 training-based AI Agent',
            ],
            'feature_flags' => [
                'monthly_message_credits' => 5000,
                'advanced_pdf' => true,
                'ai_agents' => 25,
                'api_access' => true,
                'priority_support' => 'Priority',
                'custom_integrations' => true,
                'storage_gb' => 25,
                'team_collaboration' => true,
                'advanced_analytics' => 'Advanced',
                'white_label' => false,
                'sla_guarantee' => false,
            ],
            'stripe_monthly_price_id' => env('STRIPE_STANDARD_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_STANDARD_YEARLY_PRICE_ID'),
            'monthly_price' => 4900, // $49.00
            'yearly_price' => 49000, // $490.00 (2 months free)
            'is_popular' => true,
            'limits' => [
                'agents' => 25,
                'messages_per_month' => 5000,
                'models' => 'all',
                'integrations' => 'unlimited',
                'storage_gb' => 25,
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'description' => 'Enterprise-grade solution for large organizations',
            'features' => [
                '15,000 messages per month',
                'Upload up to 10,000 pages',
                'Audio and Video files',
                '10 instruction-based agents',
                '10 training-based agents',
            ],
            'feature_flags' => [
                'monthly_message_credits' => 25000,
                'advanced_pdf' => true,
                'ai_agents' => 100,
                'api_access' => true,
                'priority_support' => 'Dedicated',
                'custom_integrations' => true,
                'storage_gb' => 100,
                'team_collaboration' => true,
                'advanced_analytics' => 'Enterprise',
                'white_label' => true,
                'sla_guarantee' => '99.9%',
            ],
            'stripe_monthly_price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
            'stripe_yearly_price_id' => env('STRIPE_PRO_YEARLY_PRICE_ID'),
            'monthly_price' => 14900, // $149.00
            'yearly_price' => 149000, // $1,490.00 (2 months free)
            'is_popular' => false,
            'limits' => [
                'agents' => 100,
                'messages_per_month' => 25000,
                'models' => 'all',
                'integrations' => 'unlimited',
                'storage_gb' => 100,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Comparison Features
    |--------------------------------------------------------------------------
    |
    | Features that will be displayed in the comparison table.
    |
    */
    'comparison_features' => [
        'instruction_ai_agents' => [
            'name' => 'Instruction-Based Agents',
            'description' => 'AI agents that follow custom instructions and prompts you define',
        ],
        'training_ai_agents' => [
            'name' => 'DocumentData-Based Agents',
            'description' => 'AI agents trained on your documents, PDFs, and knowledge base',
        ],
        'monthly_message_credits' => [
            'name' => 'Monthly Messages',
            'description' => 'Total conversations you can have with your AI agents each month',
        ],
        'advanced_pdf' => [
            'name' => 'Advanced PDF Processing',
            'description' => 'Extract and understand complex PDFs with tables, images, and layouts',
        ],
        'priority_support' => [
            'name' => 'Customer Support',
            'description' => 'Get help when you need it with dedicated support channels',
        ],
        'advanced_analytics' => [
            'name' => 'Analytics & Insights',
            'description' => 'Track usage, performance, and conversation metrics in real-time',
        ],
        'white_label' => [
            'name' => 'White Label Branding',
            'description' => 'Customize the interface with your own branding and remove ours',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Add-ons
    |--------------------------------------------------------------------------
    |
    | Additional services that users can purchase on top of their base plan.
    | Each add-on includes pricing and description information.
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
        'days' => 7,
        'package' => 'starter', // Package to trial
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
    | Configuration for UI elements and text displayed in the billing interface.
    |
    */
    'ui' => [
        'yearly_savings_percentage' => 17, // Used for "Save X%" badge
        'yearly_savings_text' => 'Save 17%',
        'most_popular_text' => 'Most Popular',
        'current_plan_text' => 'Current Plan',
        'popular_badge_text' => 'Most Popular',
        'trial_button_text' => '7-Day Free Trial',
        'money_back_guarantee_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Features
    |--------------------------------------------------------------------------
    |
    | Features that are included with all plans.
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
    | Icon mappings for each package type.
    |
    */
    'package_icons' => [
        'free' => 'gift',
        'starter' => 'zap',
        'standard' => 'star',
        'pro' => 'crown',
        'enterprise' => 'building',
    ],
];
