<?php

return [
    'cache' => [
        'ttl' => env('VECTOR_CACHE_TTL', 3600),
        'prefix' => env('VECTOR_CACHE_PREFIX', 'rag_search:'),
        'store' => env('VECTOR_CACHE_STORE', 'redis'),
    ],

    'search' => [
        // Search accuracy mode: 'default', 'high_accuracy', or 'high_speed'
        'mode' => env('VECTOR_SEARCH_MODE', 'default'),

        // Rerank is the relevance gate: chunks scoring below
        // relevance_threshold are dropped. Turning this off means every query
        // injects the top max_chunks regardless of how well they match —
        // greetings and acknowledgments will pull in noise.
        'rerank' => env('VECTOR_SEARCH_RERANK', true),

        'relevance_threshold' => env('VECTOR_SEARCH_RELEVANCE_THRESHOLD', 0.55),

        'max_chunks' => env('VECTOR_SEARCH_MAX_CHUNKS', 5),

        'candidate_limit' => env('VECTOR_SEARCH_CANDIDATE_LIMIT', 100),

        // vchordrq.probes — scale with sqrt(lists). For lists=4096, probes=64
        // is the recall sweet spot. Adjust when lists changes via vector:reindex.
        'probes' => [
            'default' => env('VECTORCHORD_DEFAULT_PROBES', 64),
            'high_accuracy' => env('VECTORCHORD_HIGH_ACCURACY_PROBES', 128),
            'high_speed' => env('VECTORCHORD_HIGH_SPEED_PROBES', 32),
        ],

        // vchordrq.epsilon — early-stop radius for the residual-quantized search.
        'epsilon' => [
            'default' => env('VECTORCHORD_DEFAULT_EPSILON', 1.9),
            'high_accuracy' => env('VECTORCHORD_HIGH_ACCURACY_EPSILON', 2.5),
            'high_speed' => env('VECTORCHORD_HIGH_SPEED_EPSILON', 1.4),
        ],
    ],

    // Threads used by vector:reindex (vchordrq build_threads). nproc is too
    // fragile to call from PHP, so pin it per environment.
    'build_threads' => env('VECTORCHORD_BUILD_THREADS', 4),

    'performance' => [
        'candidate_multiplier' => env('VECTOR_CANDIDATE_MULTIPLIER', 10),
        'max_candidates' => env('VECTOR_MAX_CANDIDATES', 5000),
    ],
];
