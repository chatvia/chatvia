<?php

return [
    'provider' => env('LLM_PROVIDER', 'scaleway'),

    'models' => [
        'vision' => 'gemma-3-27b-it',
        'embedding' => env('EMBEDDINGS_PROVIDER', 'qwen'),
        'chunker' => 'qwen3-235b-a22b-instruct-2507',

        // Generate title for conversations
        'title' => 'gemma-3-27b-it',

        'tone' => 'gemma-3-27b-it',
    ],

];
