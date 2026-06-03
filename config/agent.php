<?php

return [
    'files' => [
        'upload' => [
            'max_file_size_in_kb' => 10240, // 10MB in KB
            'max_files' => 2,
        ],
    ],

    'tools' => [
        // Hard cap on how many tool-call rounds the chat stream loop will run
        // for a single user turn. Each round = one LLM call that may emit
        // tool_calls, dispatch them, and feed results back. Prevents runaway
        // loops if a model keeps requesting tools indefinitely.
        'max_iterations' => (int) env('AGENT_TOOL_MAX_ITERATIONS', 5),

        // Global kill-switch for human-in-the-loop tool approval. When false,
        // every tool runs immediately regardless of the per-connector /
        // per-tool `requires_approval` flags — used to disable the whole
        // feature fast without touching data.
        'approval_enabled' => (bool) env('AGENT_TOOL_APPROVAL_ENABLED', true),

        // How long a pending tool-approval checkpoint stays resumable before
        // it is considered expired (the visitor walked away). Minutes.
        'approval_ttl_minutes' => (int) env('AGENT_TOOL_APPROVAL_TTL_MINUTES', 1440),
    ],

    // The LLM (matched by `llms.internal_name`) that new agents are seeded
    // with — AgentObserver wires it up on `created`. Hardcoded here for now;
    // a per-user/team override can land later without changing the observer.
    // If the configured name doesn't match any catalog row, the observer
    // falls back to the first available LLM.
    'default_model' => env('AGENT_DEFAULT_MODEL', 'qwen3.6-35b-a3b'),
];
