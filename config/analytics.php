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

    /*
    |--------------------------------------------------------------------------
    | Consolidated conversation analysis
    |--------------------------------------------------------------------------
    |
    | The scheduled app:analyze-conversations pass analyzes each conversation
    | once, this many hours after creation (end-of-life), over its full
    | transcript. Emergent-topic suggestions are surfaced once they recur across
    | at least `min_occurrences` conversations; `max_examples` caps the example
    | conversations returned per suggested label.
    |
    */

    'analysis' => [
        'scan_age_hours' => env('ANALYSIS_SCAN_AGE_HOURS', 6),
        'min_occurrences' => env('TOPIC_SUGGESTION_MIN', 3),
        'max_examples' => 10,
    ],
];
