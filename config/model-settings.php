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
                'retention' => -1,
                'context_window_enabled' => true,
                'context_window_size' => -1,
            ],
            'appearance' => [
                
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
        'theme' => 'in:light,dark',
        'notifications_enabled' => 'boolean',
        'timezone' => 'string|timezone',
        'language' => 'string|size:2',
        'items_per_page' => 'integer|min:10|max:100',
        'dashboard_layout' => 'in:grid,list',
        'message_history_enabled' => 'boolean',
        'message_history_retention' => 'integer|min:-1',
        'context_window_enabled' => 'boolean',
        'context_window_size' => 'integer|min:-1',
        'temperature' => 'numeric|min:0|max:2',
        'max_tokens' => 'integer|min:1|max:32000',
    ],
];
