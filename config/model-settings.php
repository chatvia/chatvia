<?php

use Domain\Agent\Models\Agent;

return [
    /*
    |--------------------------------------------------------------------------
    | Model Settings Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of the model settings system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for model settings.
    |
    */
    'cache' => [
        'enabled' => env('MODEL_SETTINGS_CACHE_ENABLED', true),
        'ttl' => env('MODEL_SETTINGS_CACHE_TTL', 3600), // 1 hour
        'prefix' => env('MODEL_SETTINGS_CACHE_PREFIX', 'model_settings'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Observer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of the ModelSetting observer.
    |
    */
    'observer' => [
        'enabled' => env('MODEL_SETTINGS_OBSERVER_ENABLED', true),
        'auto_cache_clearing' => env('MODEL_SETTINGS_AUTO_CACHE_CLEARING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database behavior for model settings.
    |
    */
    'database' => [
        'table' => env('MODEL_SETTINGS_TABLE', 'model_settings'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Define default settings for specific models. These will be used as
    | fallback values when a setting doesn't exist in the database.
    |
    | The key should be the full model class name, and the value should be
    | an array of setting keys with their default values.
    |
    */
    'defaults' => [
        Agent::class => [
            // Shared content settings — apply across every surface (Hosted Chat,
            // Widget, Bar). Setup writes here once and all three surfaces read
            // from it.
            'appearance' => [
                'avatar' => null,
                'logo' => null,
                'color_theme' => 'black',
                'color_shade' => 700,
                'welcome_message' => 'What can I help with? ',
                'chat_window_title' => 'Chat',
                'composer_placeholder' => 'Ask anything...',
                // Composer affordances. File uploads default off — most agents
                // don't need visitors attaching files, and owners can opt in
                // from Setup when the use case calls for it. Voice input stays
                // on by default.
                'allow_file_uploads' => false,
                'allow_voice_input' => true,
            ],
            // Bar surface — surface-specific behaviour only (text/branding now
            // come from `appearance`). Single model lives on `agents.llm_id`.
            'bar' => [
                'trigger' => 'cmd+k',
                'placement' => 'top',
            ],
            // Widget surface — surface-specific behaviour only.
            'widget' => [
                'sites' => [],
                'position' => 'bottom-right',
                'window_size' => 'large',
                'theme' => 'auto',
                'theme_color' => 'black',
                'theme_shade' => 700,
                'show_on_load' => false,
                'show_on_load_delay' => 500,
                'bubbles' => [],
                'language' => 'auto',
                'handoff_enabled' => true,
            ],
            'embed' => [
                'remove_sidebar' => false,
                'hide_sidebar_on_page_load' => false,
                'enable_theme_switcher' => true,
                'default_theme' => 'auto',
                'enable_chat_expand' => true,
                'language' => 'auto',
            ],
            // LLM hyperparameters — shared by every surface, since each agent
            // now has a single LLM (`agents.llm_id`).
            'llm_settings' => [
                'temperature' => 1.0,
                'top_k' => 0,
                'top_p' => 1.0,
                'presence_penalty' => 0.0,
                'frequency_penalty' => 0.0,
            ],
            // Per-agent data & insight switches (Setup → Settings).
            //  - chatlog: master switch for persisting conversations, on by
            //    default. Off stops StoreMessageJob, so nothing is logged — and
            //    the insights below get no data to work with.
            //  - sentiment/tone/topics: the optional LLM-powered insights,
            //    default off so owners opt in to the extra analysis. Each also
            //    gates on the plan-tier capability — a dimension runs only when
            //    the plan allows it AND the agent has switched it on.
            'analytics' => [
                'chatlog' => true,
                'sentiment' => false,
                'tone' => false,
                'topics' => false,
            ],
            'leads' => [
                'fields' => [
                    'name' => ['title' => 'Name', 'required' => true],
                    'email' => ['title' => 'Email', 'required' => true],
                    'phone' => ['title' => 'Phone Number', 'required' => false],
                    'job_title' => ['title' => 'Job Title', 'required' => false],
                    'company' => ['title' => 'Company', 'required' => false],
                    'location' => ['title' => 'Location', 'required' => false],
                    'message' => ['title' => 'Message', 'required' => false],
                ],
                'success' => [
                    'message' => 'Your information has been received and someone will get back to you soon.',
                ],
            ],
            'business' => [
                'email' => null,
                'phone' => null,
                'website' => null,
                'address' => null,
                'opening_hours' => null,
            ],
            // Per-agent AI-safety guards (Setup → Guardrails). Off by default so
            // existing agents are untouched; the owner opts in. PII redaction is
            // regex; injection/toxicity/topic use one open-weight classifier call.
            'guardrails' => [
                'enabled' => false,
                'pii' => true,            // redact emails/phones/cards/SSNs in input + output
                'injection' => true,      // block prompt-injection in input; strip from RAG sources
                'toxicity' => true,       // block toxic input; redact toxic output
                'blocked_topics' => [],   // topics to refuse; empty = no topic guard
                'allowed_scope' => '',    // optional: only answer within this described scope
                'refusal_message' => 'Sorry, I can’t help with that request.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for specific setting keys to ensure data integrity.
    | These rules can be used when validating settings before saving them.
    |
    */
    'validation' => [
        'appearance' => [
            'avatar' => ['nullable', 'image'],
            'logo' => ['nullable', 'image'],
            'color_theme' => ['nullable', 'string'],
            'color_shade' => ['nullable', 'integer', 'min:50', 'max:950'],
            'welcome_message' => ['nullable', 'string'],
            'chat_window_title' => ['nullable', 'string'],
            'composer_placeholder' => ['nullable', 'string'],
            'allow_file_uploads' => ['nullable', 'boolean'],
            'allow_voice_input' => ['nullable', 'boolean'],
        ],
        'widget' => [
            'enabled' => ['nullable', 'boolean'],
            'sites' => ['nullable', 'array'],
            'theme' => ['nullable', 'in:auto,light,dark'],
            'theme_color' => ['nullable', 'string'],
            'theme_shade' => ['nullable', 'integer', 'min:50', 'max:950'],
            'position' => ['nullable', 'in:bottom-right,bottom-left,top-right,top-left'],
            'window_size' => ['nullable', 'in:small,medium,large'],
            'primary_color' => ['nullable', 'string'],
            'show_theme_switcher' => ['nullable', 'boolean'],
            'show_on_load' => ['nullable', 'boolean'],
            'show_on_load_delay' => ['nullable', 'integer'],
            'language' => ['nullable', 'string'],
            'bubbles' => ['nullable', 'array'],
            'handoff_enabled' => ['nullable', 'boolean'],
        ],
        'embed' => [
            'remove_sidebar' => ['nullable', 'boolean'],
            'hide_sidebar_on_page_load' => ['nullable', 'boolean'],
            'enable_theme_switcher' => ['nullable', 'boolean'],
            'default_theme' => ['nullable', 'in:auto,dark,light'],
            'enable_chat_expand' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string'],
        ],
        'business' => [
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:1000'],
        ],
        'bar' => [
            'trigger' => ['nullable', 'in:cmd+k,cmd+j,cmd+/,/,shift+/'],
            'placement' => ['nullable', 'in:top,top-right,bottom,bottom-right'],
        ],
        'llm_settings' => [
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'top_k' => ['nullable', 'integer', 'min:0', 'max:100'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'presence_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
        ],
        'analytics' => [
            'chatlog' => ['nullable', 'boolean'],
            'sentiment' => ['nullable', 'boolean'],
            'tone' => ['nullable', 'boolean'],
            'topics' => ['nullable', 'boolean'],
        ],
        'guardrails' => [
            'enabled' => ['nullable', 'boolean'],
            'pii' => ['nullable', 'boolean'],
            'injection' => ['nullable', 'boolean'],
            'toxicity' => ['nullable', 'boolean'],
            'blocked_topics' => ['nullable', 'array'],
            'blocked_topics.*' => ['string', 'max:120'],
            'allowed_scope' => ['nullable', 'string', 'max:2000'],
            'refusal_message' => ['nullable', 'string', 'max:500'],
        ],
    ],
];
