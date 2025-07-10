<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vector Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for optimizing vector search performance
    |
    */

    'cache' => [
        'ttl' => env('VECTOR_CACHE_TTL', 3600), // 1 hour default
        'prefix' => env('VECTOR_CACHE_PREFIX', 'rag_search:'),
        'store' => env('VECTOR_CACHE_STORE', 'redis'), // Use Redis for better performance
    ],

    'search' => [
        'default_limit' => env('VECTOR_SEARCH_DEFAULT_LIMIT', 3),
        'max_limit' => env('VECTOR_SEARCH_MAX_LIMIT', 50),
        'candidate_limit' => env('VECTOR_SEARCH_CANDIDATE_LIMIT', 100),
    ],

    'hnsw' => [
        'm' => env('VECTOR_HNSW_M', 24), // Higher for better recall, lower for speed
        'ef_construction' => env('VECTOR_HNSW_EF_CONSTRUCTION', 400),
        'ef_search' => env('VECTOR_HNSW_EF_SEARCH', 100), // Runtime search parameter
    ],

    'postgresql' => [
        'work_mem' => env('POSTGRES_WORK_MEM', '256MB'),
        'shared_buffers' => env('POSTGRES_SHARED_BUFFERS', '1GB'),
        'effective_cache_size' => env('POSTGRES_EFFECTIVE_CACHE_SIZE', '4GB'),
        'random_page_cost' => env('POSTGRES_RANDOM_PAGE_COST', 1.1), // Lower for SSD
        'maintenance_work_mem' => env('POSTGRES_MAINTENANCE_WORK_MEM', '512MB'),
    ],

    'performance' => [
        'enable_parallel_queries' => env('VECTOR_ENABLE_PARALLEL', true),
        'connection_pool_size' => env('VECTOR_CONNECTION_POOL_SIZE', 20),
        'enable_query_cache' => env('VECTOR_ENABLE_QUERY_CACHE', true),
        'enable_embedding_cache' => env('VECTOR_ENABLE_EMBEDDING_CACHE', true),
    ],

    'monitoring' => [
        'log_slow_queries' => env('VECTOR_LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('VECTOR_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'enable_metrics' => env('VECTOR_ENABLE_METRICS', false),
    ],
];
