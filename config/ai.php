<?php

return [
    'provider' => env('LLM_PROVIDER', 'scaleway'),

    'models' => [
        'vision' => 'gemma-3-27b-it',

        'embedding' => 'qwen',

        'tables' => 'gemma-3-27b-it',

        'chunker' => 'llama-3.1-70b-instruct',

        'title' => 'qwen3.6-35b-a3b',

        'subdomain' => 'mistral-nemo-instruct-2407',

        'tone' => 'llama-3.1-8b-instruct',

        'topic' => 'llama-3.1-8b-instruct',

        'search_decision' => 'gemma-3-27b-it',
    ],

    'query' => [
        'rewriter' => [
            'enabled' => env('QUERY_REWRITER_ENABLED', false),
            'model' => 'gemma-3-27b-it',
        ],
    ],

    'pricing' => [
        'chunker_input_per_million_tokens' => 0.90,
        'chunker_output_per_million_tokens' => 0.90,
        'embedding_output_per_million_tokens' => 0.90,
    ],

    'chunker' => [
        'enhance' => env('CHUNKER_ENHANCE', false),
    ],
];
