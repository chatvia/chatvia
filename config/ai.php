<?php

return [
    'provider' => env('LLM_PROVIDER', 'scaleway'),

    'models' => [
        'vision' => 'gemma-3-27b-it',
        'embedding' => 'qwen',
        'chunker' => 'llama-3.3-70b-instruct',
        'title' => 'gemma-3-27b-it',
        'tone' => 'gemma-3-27b-it',
        'search_decision' => 'gemma-3-27b-it',
    ],

    'query' => [
        'rewriter' => [
            'enabled' => env('QUERY_REWRITER_ENABLED', false),
            'model' => 'gemma-3-27b-it',
        ]
    ],

    'pricing' => [
        'chunker_per_million_tokens' => 0.90,
        'embedding_per_million_tokens' => 0.90,
    ],

];
