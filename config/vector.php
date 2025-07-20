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
        'candidate_multiplier' => env('VECTOR_CANDIDATE_MULTIPLIER', 10),
        'max_candidates' => env('VECTOR_MAX_CANDIDATES', 5000),
    ],

    'indexing' => [
        /*
        |--------------------------------------------------------------------------
        | Index Build Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for building VectorChord indexes with optimal performance
        |
        */

        // Enable residual quantization for better compression and performance
        'residual_quantization' => env('VECTORCHORD_RESIDUAL_QUANTIZATION', true),

        // Spherical centroids configuration (false is recommended for most cases)
        'spherical_centroids' => env('VECTORCHORD_SPHERICAL_CENTROIDS', false),

        // Number of build threads for parallel index construction
        'build_threads' => env('VECTORCHORD_BUILD_THREADS', 4),

        // External build configuration (recommended for large datasets)
        'external_build' => [
            'enabled' => env('VECTORCHORD_EXTERNAL_BUILD', false),
            'k_means_iterations' => env('VECTORCHORD_KMEANS_ITERATIONS', 25),
            'use_gpu' => env('VECTORCHORD_USE_GPU', false),
        ],

        // Dynamic list calculation based on dataset size
        // Formula: 4√n ≤ lists ≤ 8√n where n is total number of vectors
        // Note: VectorChord requires lists > 0. If index was created with 0 lists,
        // you'll get "need 0 probes, but X probes provided" errors. Rebuild index if this happens.
        'lists' => [
            'auto_calculate' => env('VECTORCHORD_AUTO_CALCULATE_LISTS', true),
            'min_multiplier' => env('VECTORCHORD_MIN_LIST_MULTIPLIER', 4),
            'max_multiplier' => env('VECTORCHORD_MAX_LIST_MULTIPLIER', 8),
            'fallback' => env('VECTORCHORD_FALLBACK_LISTS', 100), // Minimum of 100 lists
            'absolute_minimum' => 100, // Never go below this value
        ],
    ],

    'search' => [
        /*
        |--------------------------------------------------------------------------
        | Search Runtime Configuration
        |--------------------------------------------------------------------------
        |
        | Runtime parameters that control search performance vs accuracy trade-offs
        |
        | PROBES: Number of clusters to search during query
        | - More probes = higher accuracy but slower queries
        | - Fewer probes = faster queries but potentially lower accuracy
        | - Rule of thumb: probes should be 10-20% of total lists
        |
        */

        // Number of probes (clusters to search) for different accuracy levels
        'probes' => [
            'default' => env('VECTORCHORD_DEFAULT_PROBES', 20),        // Balanced performance
            'high_accuracy' => env('VECTORCHORD_HIGH_ACCURACY_PROBES', 50),  // Best results
            'high_speed' => env('VECTORCHORD_HIGH_SPEED_PROBES', 10),        // Fastest queries
        ],

        // MaxSim configuration for multi-vector search (VectorChord 0.3+)
        // Used for advanced search methods like ColBERT
        'maxsim' => [
            'enabled' => env('VECTORCHORD_MAXSIM_ENABLED', false),
            'refine' => env('VECTORCHORD_MAXSIM_REFINE', 2000),  // Number of vectors to refine
        ],

        // Additional search optimizations
        'optimization' => [
            'use_disk_index' => env('VECTORCHORD_USE_DISK_INDEX', true),
            'enable_parallel_search' => env('VECTORCHORD_ENABLE_PARALLEL_SEARCH', true),
        ],
    ],

    'quantization' => [
        /*
        |--------------------------------------------------------------------------
        | Quantization Configuration
        |--------------------------------------------------------------------------
        |
        | Vector quantization settings for memory optimization
        |
        */

        // RabitQ quantization settings (VectorChord's advanced quantization)
        'rabitq' => [
            'enabled' => env('VECTORCHORD_RABITQ_ENABLED', true),
            'bits_per_dimension' => env('VECTORCHORD_RABITQ_BITS', 1), // 1-bit quantization
        ],

        // Alternative quantization options
        'scalar_quantization' => [
            'enabled' => env('VECTORCHORD_SCALAR_QUANTIZATION', false),
            'type' => env('VECTORCHORD_SCALAR_TYPE', 'int8'),
        ],

        // Float16 support for reduced memory usage
        'float16' => [
            'enabled' => env('VECTORCHORD_FLOAT16_ENABLED', false),
        ],
    ],

    'performance' => [
        /*
        |--------------------------------------------------------------------------
        | Performance Tuning
        |--------------------------------------------------------------------------
        |
        | Performance-related configuration options
        |
        */

        // Memory management
        'memory' => [
            'shared_buffers_ratio' => env('VECTORCHORD_SHARED_BUFFERS_RATIO', 0.25), // 25% of RAM
            'work_mem' => env('VECTORCHORD_WORK_MEM', '256MB'),
            'maintenance_work_mem' => env('VECTORCHORD_MAINTENANCE_WORK_MEM', '1GB'),
        ],

        // Connection and concurrency settings
        'connections' => [
            'max_connections' => env('VECTORCHORD_MAX_CONNECTIONS', 100),
            'pool_size' => env('VECTORCHORD_POOL_SIZE', 20),
        ],

        // Batch processing configuration
        'batch' => [
            'insert_batch_size' => env('VECTORCHORD_INSERT_BATCH_SIZE', 1000),
            'search_batch_size' => env('VECTORCHORD_SEARCH_BATCH_SIZE', 100),
        ],
    ],

    'monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Monitoring and Metrics
        |--------------------------------------------------------------------------
        |
        | Configuration for monitoring VectorChord performance
        |
        */

        'slow_query_threshold' => env('VECTORCHORD_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'metrics_endpoint' => env('VECTORCHORD_METRICS_ENDPOINT', '/metrics'),
    ],

    'compatibility' => [
        /*
        |--------------------------------------------------------------------------
        | Compatibility Settings
        |--------------------------------------------------------------------------
        |
        | Settings for compatibility with other extensions
        |
        */

        'pgvector_compatibility' => env('VECTORCHORD_PGVECTOR_COMPATIBILITY', false),
        'migration_mode' => env('VECTORCHORD_MIGRATION_MODE', false),
    ],

    'datasets' => [
        /*
        |--------------------------------------------------------------------------
        | Dataset-Specific Configurations
        |--------------------------------------------------------------------------
        |
        | Predefined configurations for different dataset sizes
        |
        */

        'small' => [
            'max_vectors' => 100000,
            'recommended_lists' => 100,
            'recommended_probes' => 10,
        ],

        'medium' => [
            'max_vectors' => 1000000,
            'recommended_lists' => 1000,
            'recommended_probes' => 20,
        ],

        'large' => [
            'max_vectors' => 10000000,
            'recommended_lists' => 5000,
            'recommended_probes' => 30,
        ],

        'xlarge' => [
            'max_vectors' => 100000000,
            'recommended_lists' => 16000,
            'recommended_probes' => 50,
        ],
    ],
];
