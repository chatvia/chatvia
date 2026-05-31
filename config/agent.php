<?php

return [
    'files' => [
        'upload' => [
            'max_file_size_in_kb' => 10240, // 10MB in KB
            'max_files' => 2,
        ],
    ],

    // The LLM (matched by `llms.internal_name`) that new agents are seeded
    // with — AgentObserver wires it up on `created`. Hardcoded here for now;
    // a per-user/team override can land later without changing the observer.
    // If the configured name doesn't match any catalog row, the observer
    // falls back to the first available LLM.
    'default_model' => env('AGENT_DEFAULT_MODEL', 'qwen3-235b-a22b-instruct-2507'),
];
