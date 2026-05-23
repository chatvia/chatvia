<?php

return [

    'url' => env('SSE_URL', 'http://localhost:8000/sse'),

    /*
    |--------------------------------------------------------------------------
    | Envelope v1 feature flag
    |--------------------------------------------------------------------------
    |
    | When true, ChatStreamService routes through the typed envelope path
    | (SseStreamer + Source generators). When false, the legacy StreamHandler
    | path is used. Flip on per-environment to migrate incrementally.
    |
    */

    'envelope_v1' => env('SSE_ENVELOPE_V1', true),

    /*
    |--------------------------------------------------------------------------
    | Request-id idempotency
    |--------------------------------------------------------------------------
    |
    | Streams keyed by a client-generated request_id are remembered in this
    | cache store for `ttl_seconds`. A replay request with the same id will
    | re-stream the stored snapshot instead of running the LLM again.
    |
    | `store` is a cache driver name from config/cache.php. Leave null to
    | use the default store.
    |
    */

    'dedupe' => [
        'store' => env('SSE_DEDUPE_STORE'),
        'ttl_seconds' => (int) env('SSE_DEDUPE_TTL', 600),
    ],

];
