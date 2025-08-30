<?php

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
        \App\Models\Agent::class => [
            'message_history' => [
                'retention' => 30,
                'context_window_enabled' => true,
                'context_window_size' => -1,
            ],
            'appearance' => [
                'avatar' => null,
                'logo' => null,
                'color_theme' => 'black',
                'color_shade' => 700,
                'welcome_message' => 'What can I help with? ',
                'chat_window_title' => 'Chat',
                'chat_footer_text' => 'Consider checking important information',
                'composer_placeholder' => 'Ask anything...',
            ],
            'widget' => [
                'sites' => [],
                'position' => 'bottom-right',
                'window_size' => 'large',
                'theme_color' => 'black',
                'theme_shade' => 700,
                'show_on_load' => false,
                'show_on_load_delay' => 500,
                'model' => 'gpt-5-mini',
                'bubbles' => [],
            ],
            'embed' => [
                'remove_sidebar' => false,
                'hide_sidebar_on_page_load' => false,
                'enable_theme_switcher' => true,
                'default_theme' => 'auto',
                'enable_chat_expand' => true,
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
        'message_history' => [
            'retention' => ['nullable', 'integer'],
            'context_window_enabled' => ['nullable', 'boolean'],
            'context_window_size' => ['nullable', 'integer'],
        ],
        'appearance' => [
            'avatar' => ['nullable', 'image'],
            'logo' => ['nullable', 'image'],
            'color_theme' => ['nullable', 'string'],
            'color_shade' => ['nullable', 'integer', 'min:50', 'max:950'],
            'welcome_message' => ['nullable', 'string'],
            'chat_window_title' => ['nullable', 'string'],
            'chat_footer_text' => ['nullable', 'string'],
            'composer_placeholder' => ['nullable', 'string'],
        ],
        'widget' => [
            'enabled' => ['nullable', 'boolean'],
            'sites' => ['nullable', 'array'],
            'theme' => ['nullable', 'in:auto,light,dark'],
            'position' => ['nullable', 'in:bottom-right,bottom-left,top-right,top-left'],
            'window_size' => ['nullable', 'in:small,medium,large'],
            'primary_color' => ['nullable', 'string'],
            'show_theme_switcher' => ['nullable', 'boolean'],
            'show_on_load' => ['nullable', 'boolean'],
            'show_on_load_delay' => ['nullable', 'integer'],
        ],
        'embed' => [
            'remove_sidebar' => ['nullable', 'boolean'],
            'hide_sidebar_on_page_load' => ['nullable', 'boolean'],
            'enable_theme_switcher' => ['nullable', 'boolean'],
            'default_theme' => ['nullable', 'in:auto,dark,light'],
            'enable_chat_expand' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string'],
        ],
    ],
];
