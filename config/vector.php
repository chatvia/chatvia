<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vector Search Configuration
    |--------------------------------------------------------------------------
    |
    | VectorChord (pgvecto.rs) configuration for optimized vector search
    |
    */

    'cache' => [
        'ttl' => env('VECTOR_CACHE_TTL', 3600), // Cache embeddings for 1 hour
        'prefix' => env('VECTOR_CACHE_PREFIX', 'rag_search:'),
        'store' => env('VECTOR_CACHE_STORE', 'redis'), // Redis recommended for performance
    ],

    'search' => [
        // Search accuracy mode: 'default', 'high_accuracy', or 'high_speed'
        'mode' => env('VECTOR_SEARCH_MODE', 'default'),

        // Enable Cohere reranking for better relevance
        'rerank' => env('VECTOR_SEARCH_RERANK', false),

        // Relevance score threshold for reranking (0.0 to 1.0)
        // Only chunks with relevance_score >= threshold are included
        // Lower = more permissive, Higher = more strict
        'relevance_threshold' => env('VECTOR_SEARCH_RELEVANCE_THRESHOLD', 0.55),

        // Maximum number of chunks to return (default: 3)
        'max_chunks' => env('VECTOR_SEARCH_MAX_CHUNKS', 3),

        // Maximum number of initial candidates before filtering
        'candidate_limit' => env('VECTOR_SEARCH_CANDIDATE_LIMIT', 100),

        // Number of probes (clusters to search) for different accuracy levels
        // Higher probes = better accuracy but slower
        'probes' => [
            'default' => env('VECTORCHORD_DEFAULT_PROBES', 20),
            'high_accuracy' => env('VECTORCHORD_HIGH_ACCURACY_PROBES', 50),
            'high_speed' => env('VECTORCHORD_HIGH_SPEED_PROBES', 10),
        ],
    ],

    'quantization' => [
        // RabitQ: VectorChord's binary quantization for 32x memory reduction
        'rabitq' => [
            'enabled' => env('VECTORCHORD_RABITQ_ENABLED', true),
        ],
    ],

    'performance' => [
        // Candidate multiplier for initial search
        'candidate_multiplier' => env('VECTOR_CANDIDATE_MULTIPLIER', 10),

        // Maximum candidates to prevent memory issues
        'max_candidates' => env('VECTOR_MAX_CANDIDATES', 5000),
    ],
];
