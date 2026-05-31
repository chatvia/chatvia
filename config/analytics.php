<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics Master Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, dispatched analytics events are silently dropped before
    | hitting the queue. Use this to turn the whole subsystem off without
    | touching code (CI runs, local debugging, partial outages).
    |
    */

    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Storage Connection
    |--------------------------------------------------------------------------
    |
    | Database connection used for the analytics_* tables. Set to a dedicated
    | connection (defined in config/database.php) to isolate analytics writes
    | from the main app database. Defaults to the framework default.
    |
    | A pre-wired "analytics" connection is provided in config/database.php;
    | point DB_ANALYTICS_DATABASE at a separate Postgres database to use it.
    |
    */

    'connection' => env('ANALYTICS_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Listeners run on this queue. Keep it separate from the chat-critical
    | `messages` queue so analytics backlog never blocks a user reply.
    |
    */

    'queue' => env('ANALYTICS_QUEUE', 'analytics'),

    /*
    |--------------------------------------------------------------------------
    | Sentinels for the rollup primary key
    |--------------------------------------------------------------------------
    |
    | Postgres composite primary keys reject NULL, so the hourly rollup uses
    | these placeholder values when the dimension is unknown at write time.
    | Read paths translate them back to "Unknown" / NULL in responses.
    |
    */

    'unknown_country_code' => '--',
    'unknown_llm_external_name' => '',
];
