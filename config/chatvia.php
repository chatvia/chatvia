<?php

/*
|--------------------------------------------------------------------------
| Chatvia
|--------------------------------------------------------------------------
|
| Single home for Chatvia's app-specific configuration. Each top-level key
| below is a bounded area (agent, ai, vector, analytics, sse, hmac, widget)
| that used to live in its own config file; they were consolidated here so
| there's one place to look. Reference values with the `chatvia.` prefix,
| e.g. config('chatvia.ai.provider'), config('chatvia.vector.search.mode').
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Languages (shared)
    |--------------------------------------------------------------------------
    |
    | The unified language list. Both the widget (chatvia.widget.available_languages)
    | and the hosted-chat selector read from this — set it once here.
    |
    */

    'languages' => [
        'auto' => 'Auto',
        'en' => 'English',
        'ar' => 'Arabic (العربية)',
        'da' => 'Danish (Dansk)',
        'de' => 'German (Deutsch)',
        'es' => 'Spanish (Español)',
        'fa' => 'Persian (فارسی)',
        'fr' => 'French (Français)',
        'hi' => 'Hindi (हिन्दी)',
        'id' => 'Indonesian (Bahasa Indonesia)',
        'ja' => 'Japanese (日本語)',
        'ko' => 'Korean (한국어)',
        'pt' => 'Portuguese (Português)',
        'ru' => 'Russian (Русский)',
        'tr' => 'Turkish (Türkçe)',
        'vi' => 'Vietnamese (Tiếng Việt)',
        'zh' => 'Chinese (中文)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent
    |--------------------------------------------------------------------------
    |
    | Per-agent runtime limits: file uploads, the tool-call loop, approvals, and
    | the LLM new agents are seeded with.
    |
    */

    'agent' => [
        'files' => [
            'upload' => [
                'max_file_size_in_kb' => 10240, // 10MB in KB
                'max_files' => 1,

                // Hard cap on PDF pages for a single chat attachment. The whole
                // document is sent to Mistral for Q&A, so this bounds cost and
                // latency. Enforced at upload via MaxPdfPages.
                'max_pdf_pages' => 10,
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

        // How many recent conversation messages (user + assistant turns) to
        // replay into the LLM context on each turn. Caps the history window so
        // it stays bounded as a chat grows — older messages are dropped. Raise
        // it to give the model more follow-up context, lower it to save tokens.
        'history_messages' => (int) env('AGENT_HISTORY_MESSAGES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI / LLM
    |--------------------------------------------------------------------------
    |
    | Provider selection, the model assigned to each internal task, query
    | rewriting, ingestion pricing, and the chunk-enhance switch.
    |
    */

    'ai' => [
        'provider' => env('LLM_PROVIDER', 'scaleway'),

        // https://www.scaleway.com/en/docs/generative-apis/reference-content/supported-models/
        'models' => [
            'vision' => 'gemma-3-27b-it',
            'embedding' => 'qwen',
            'tables' => 'mistral-small-3.2-24b-instruct-2506',
            'chunker' => 'llama-3.1-70b-instruct',
            'title' => 'mistral-small-3.2-24b-instruct-2506',
            'subdomain' => 'mistral-small-3.2-24b-instruct-2506',
            'tone' => 'mistral-small-3.2-24b-instruct-2506',
            'topic' => 'mistral-small-3.2-24b-instruct-2506',
            'search_decision' => 'gemma-3-27b-it',
            // LLM-as-judge for agent evals. Open-weight + reliable for the
            // constrained pass/fail scoring we ask of it; swap to a Qwen3
            // instruct model here without touching code.
            'judge' => 'mistral-small-3.2-24b-instruct-2506',

            // Open-weight moderation/guard classifier: ONE structured call returns
            // the prompt-injection / off-topic / toxicity verdicts for a message.
            // PII detection is regex (free), not this model.
            'guardrail' => 'mistral-small-3.2-24b-instruct-2506',
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector search (VectorChord / RAG)
    |--------------------------------------------------------------------------
    |
    | Embedding-query cache, hybrid-search tuning, and the vchordrq build/probe
    | parameters. NOTE: KnowledgeBase fetches the whole `chatvia.vector` array.
    |
    */

    'vector' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Master switch, the (optionally separate) storage connection, the queue
    | listeners run on, rollup sentinels, and the conversation-analysis pass.
    |
    */

    'analytics' => [
        // When disabled, dispatched analytics events are silently dropped before
        // hitting the queue (CI runs, local debugging, partial outages).
        'enabled' => env('ANALYTICS_ENABLED', true),

        // Database connection for the analytics_* tables. A pre-wired "analytics"
        // connection lives in config/database.php; point DB_ANALYTICS_DATABASE at
        // a separate Postgres database to isolate analytics writes. Null = default.
        'connection' => env('ANALYTICS_DB_CONNECTION'),

        // Listeners run on this queue. Keep it separate from the chat-critical
        // `messages` queue so analytics backlog never blocks a user reply.
        'queue' => env('ANALYTICS_QUEUE', 'analytics'),

        // Postgres composite primary keys reject NULL, so the hourly rollup uses
        // these placeholders when a dimension is unknown at write time. Read paths
        // translate them back to "Unknown" / NULL in responses.
        'unknown_country_code' => '--',
        'unknown_llm_external_name' => '',

        // The scheduled app:analyze-conversations pass analyzes each conversation
        // once, `scan_age_hours` after creation, over its full transcript.
        // Emergent topics surface once they recur across `min_occurrences`
        // conversations; `max_examples` caps the examples returned per label.
        'analysis' => [
            'scan_age_hours' => env('ANALYSIS_SCAN_AGE_HOURS', 6),
            'min_occurrences' => env('TOPIC_SUGGESTION_MIN', 3),
            'max_examples' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance (tool-call audit + access)
    |--------------------------------------------------------------------------
    |
    | The Governed MCP Gateway records an immutable row in the dedicated
    | agent_activity table for every agent tool call, and can gate/rate-limit
    | calls at dispatch. Audit is on by default and silent; arguments/results
    | are redacted + size-capped before storage.
    |
    */

    'governance' => [
        // Master switch for tool-call audit. When false, RecordToolCallAudit
        // drops the event before it touches the DB (CI, local debugging).
        'audit_enabled' => env('GOVERNANCE_AUDIT_ENABLED', true),

        // How long audit rows (agent_activity) are kept before the daily
        // app:prune-agent-activity sweep removes them.
        'retention_days' => (int) env('GOVERNANCE_RETENTION_DAYS', 90),

        // Size caps (bytes) for the stored tool arguments / result text. The
        // full raw args are hashed (sha256) BEFORE truncation, for integrity.
        'max_arg_bytes' => (int) env('GOVERNANCE_MAX_ARG_BYTES', 4096),
        'max_result_bytes' => (int) env('GOVERNANCE_MAX_RESULT_BYTES', 8192),

        // Argument keys whose values are replaced with "[redacted]" before the
        // audit row is written (defends against secrets/PII echoed in args).
        'redact_keys' => ['password', 'token', 'secret', 'authorization', 'api_key', 'apikey', 'access_token'],

        // Per-agent-per-tool rate limit: max tool calls allowed per window.
        // Generous by default so it stays invisible until a customer tightens it.
        'tool_rate_limit' => (int) env('GOVERNANCE_TOOL_RATE_LIMIT', 60),
        'tool_rate_window_seconds' => (int) env('GOVERNANCE_TOOL_RATE_WINDOW', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent evaluation (#9 Agent Governance + Evals)
    |--------------------------------------------------------------------------
    |
    | Per-agent eval suites run each test case through the agent's REAL pipeline
    | (system prompt + RAG + tools) off-request and score it. Runs are queued so
    | a suite never blocks a chat reply.
    |
    */

    'evals' => [
        // Master switch — when false, eval runs are rejected (CI / local).
        'enabled' => env('EVALS_ENABLED', true),

        // Queue the per-case eval jobs run on — off the chat-critical `messages` queue.
        'queue' => env('EVALS_QUEUE', 'analytics'),

        // Hard cap on cases per run, so a misconfigured suite can't burn the
        // workspace's LLM budget.
        'max_cases_per_run' => (int) env('EVALS_MAX_CASES', 100),

        // Size cap (bytes) for the stored actual_output; a sha256 of the raw is kept.
        'max_output_bytes' => (int) env('EVALS_MAX_OUTPUT_BYTES', 8192),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardrails (#? AI Safety)
    |--------------------------------------------------------------------------
    |
    | Input + output safety guards on the live chat: PII (regex redaction),
    | prompt-injection, off-topic/blocked-topics, and toxicity (one open-weight
    | classifier call). Per-agent toggles live in the `guardrails` model-settings
    | group; triggers are logged to the immutable `guardrail_events` table.
    |
    */

    'guardrails' => [
        // Master switch — when false, no guard runs and the chat path is untouched.
        'enabled' => env('GUARDRAILS_ENABLED', true),

        // Queue the audit-logging listener runs on (off the chat-critical path).
        'queue' => env('GUARDRAILS_QUEUE', 'analytics'),

        // Retention for the immutable guardrail_events log.
        'retention_days' => (int) env('GUARDRAILS_RETENTION_DAYS', 90),

        // The redacted snippet stored on a trigger is capped to this many bytes;
        // the full original text is hashed (sha256) before truncation, never stored raw.
        'max_snippet_bytes' => (int) env('GUARDRAILS_MAX_SNIPPET_BYTES', 2048),

        // Phrases that mark a likely prompt-injection inside retrieved RAG source
        // content (a cheap rules pass; the LLM classifier handles the user message).
        'injection_markers' => [
            'ignore previous instructions',
            'ignore the above',
            'disregard previous',
            'disregard all previous',
            'forget previous instructions',
            'you are now',
            'new instructions:',
            'override your instructions',
            'system prompt:',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SSE streaming
    |--------------------------------------------------------------------------
    |
    | The SSE endpoint and the request-id idempotency store. Streams keyed by a
    | client request_id are remembered in `dedupe.store` for `ttl_seconds`; a
    | replay with the same id re-streams the snapshot instead of re-running the LLM.
    |
    */

    'sse' => [
        'url' => env('SSE_URL', 'http://localhost:8000/sse'),

        'dedupe' => [
            // Cache driver name from config/cache.php; null = default store.
            'store' => env('SSE_DEDUPE_STORE'),
            'ttl_seconds' => (int) env('SSE_DEDUPE_TTL', 600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HMAC
    |--------------------------------------------------------------------------
    |
    | Shared secret for request signing. Currently not referenced anywhere in the
    | codebase — kept here as a reserved key; remove once confirmed unneeded.
    |
    */

    'hmac' => [
        'key' => env('HMAC_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget (embeddable chat)
    |--------------------------------------------------------------------------
    |
    | The embed script URL, the demo widget id, and the language picker — which
    | reads the shared `chatvia.languages` list above (no duplicate copy).
    |
    */

    'widget' => [
        'script_url_embed' => env('WIDGET_SCRIPT_URL', 'http://localhost:3000/dist/embed.js'),
        'chatvia_widget_id' => env('CHATVIA_WIDGET_ID'),

        // Global per-agent ceilings for the LLM-cost widget stream, keyed on the
        // server-resolved agent so a client cannot multiply its budget (and burn
        // the owner's quota/spend) by rotating X-SESSION-ID or source IPs.
        'stream_per_agent_per_minute' => (int) env('WIDGET_STREAM_PER_AGENT_PER_MINUTE', 120),
        'stream_per_agent_per_hour' => (int) env('WIDGET_STREAM_PER_AGENT_PER_HOUR', 1500),
    ],

];
