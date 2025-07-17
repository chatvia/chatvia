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
                'primary_color' => '#000000',
                'chat_bubble_color' => '#ffffff',
                'chat_text_color' => '#000000',
                'welcome_message' => 'What can I help with? ',
                'chat_window_title' => 'Chat',
                'chat_footer_text' => 'Consider checking important information',
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
            'primary_color' => ['nullable', 'hex_color'],
            'chat_bubble_color' => ['nullable', 'hex_color'],
            'chat_text_color' => ['nullable', 'hex_color'],
            'welcome_message' => ['nullable', 'string'],
            'chat_window_title' => ['nullable', 'string'],
            'chat_footer_text' => ['nullable', 'string'],
        ],
    ],
];
