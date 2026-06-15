<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scramble — Chatvia Public API documentation
    |--------------------------------------------------------------------------
    |
    | Generates the OpenAPI 3.1 spec for our customer-facing API. Docs live
    | at `/docs/api` and the raw spec is at `/docs/api.json`.
    |
    | We scope discovery to /api/v1/* in AppServiceProvider via
    | `Scramble::routes(...)` — the `api_path` below is informational only.
    */

    'api_path' => 'api/v1',
    'api_domain' => null,
    'export_path' => 'api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'Welcome',

    ],

    'ui' => [
        'title' => 'Chatvia API',
        'theme' => 'light',
        'hide_try_it' => false,
        'hide_schemas' => false,
        'logo' => '',
        'try_it_credentials_policy' => 'include',
        'layout' => 'responsive',
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
    ],

    'extensions' => [],
];
