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
    | Founder
    |--------------------------------------------------------------------------
    |
    | Identity used for the personal welcome note sent to new trial sign-ups.
    | The email is set as the message Reply-To so replies land in the founder's
    | inbox — override it via env if the address changes.
    |
    */

    'founder' => [
        'name' => env('FOUNDER_NAME', 'Ahmad Mayahi'),
        'email' => env('FOUNDER_EMAIL', 'info@chatvia.ai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages (shared)
    |--------------------------------------------------------------------------
    |
    | The unified language list. Both the widget (chatvia.widget.available_languages)
    | and the hosted-chat selector read from this — set it once here. It is also the
    | allow-list for a user's saved dashboard locale (UpdatePreferencesRequest /
    | SetLocale), so every dashboard UI language (resources/js/i18n SUPPORTED_LOCALES)
    | must appear here. Ordered EU-first to match the dashboard switcher; 'auto' (let
    | the agent detect the visitor's language) stays first.
    |
    */

    'languages' => [
        'auto' => 'Auto',
        'en' => 'English',
        'de' => 'German (Deutsch)',
        'fr' => 'French (Français)',
        'es' => 'Spanish (Español)',
        'pt' => 'Portuguese (Português)',
        'pl' => 'Polish (Polski)',
        'da' => 'Danish (Dansk)',
        'sv' => 'Swedish (Svenska)',
        'fi' => 'Finnish (Suomi)',
        'no' => 'Norwegian (Norsk)',
        'ar' => 'Arabic (العربية)',
        'fa' => 'Persian (فارسی)',
        'hi' => 'Hindi (हिन्दी)',
        'id' => 'Indonesian (Bahasa Indonesia)',
        'ja' => 'Japanese (日本語)',
        'ko' => 'Korean (한국어)',
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

            // Per-turn tool routing. The hybrid search (ToolHybridSearch) recalls
            // candidate tools and ranks them by RRF — great for ordering, but RRF
            // is a rank-fusion score with no absolute "is this relevant" meaning,
            // so the top tool scores about the same for "hi" as for a real request.
            // The relevance gate adds the missing signal: a reranker scores each
            // candidate against the user's message and anything below the threshold
            // is dropped. When nothing clears the bar (a greeting, a thank-you), the
            // turn ships ZERO tools — the `tools` array is omitted entirely, so the
            // model can't see, list, or advertise an off-topic tool. This is the
            // tool-side mirror of chatvia.vector.search.relevance_threshold, which
            // already keeps greetings from pulling in irrelevant knowledge chunks.
            'routing' => [
                // Master switch. Off = recall-only (every tool-bearing turn ships
                // its top hybrid hits, the old availability-driven behaviour).
                'relevance_gate' => (bool) env('AGENT_TOOL_RELEVANCE_GATE', true),

                // A tool must score at/above this (reranker relevance, 0..1) to
                // ship. Higher = stricter. Tune against real traffic via the
                // ToolRelevanceGate debug log ("kept"/"dropped" with scores) or
                // the offline harness (tests/Eval/ToolRoutingEvalTest). Acts as
                // the shared fallback for the per-scope thresholds below.
                'relevance_threshold' => (float) env('AGENT_TOOL_RELEVANCE_THRESHOLD', 0.55),

                // Per-scope gating. A wrong READ is cheap (the user re-asks); a
                // wrong WRITE executes — so read tools get a looser bar (recall)
                // and write/delete tools the shared, stricter `relevance_threshold`
                // (precision). Tuned against the offline eval harness
                // (tests/Eval/ToolRoutingEvalTest): raising the WRITE bar above the
                // shared value trades wrong-tool suppression ~1:1 for starving the
                // correct write (the reranker scores correct and wrong writes in the
                // same band), so it stays at the shared default. See ToolRelevanceGate.
                'relevance_threshold_read' => (float) env('AGENT_TOOL_RELEVANCE_THRESHOLD_READ', 0.45),
                'relevance_threshold_write' => (float) env('AGENT_TOOL_RELEVANCE_THRESHOLD_WRITE', env('AGENT_TOOL_RELEVANCE_THRESHOLD', 0.55)),

                // EXPERIMENTAL (off by default). A small "middle model" connector
                // router: after the relevance gate, a cheap structured LLM call
                // (config('chatvia.ai.models.connector_router')) picks which
                // connector(s) the turn is about, and write tools from connectors
                // it didn't pick are dropped. A reasoning model can separate
                // "ticket → support" from "issue → tracker" where the embedding
                // reranker's scores bunched. Costs a serial LLM hop per tool-bearing
                // turn; measure it against tests/Eval before turning on. Fails open.
                'connector_classifier' => (bool) env('AGENT_TOOL_CONNECTOR_CLASSIFIER', false),

                // How many hybrid-search candidates to send to the reranker. Caps
                // the rerank payload/latency; past the top dozen the cross-encoder
                // rarely promotes a tool the RRF recall didn't already surface.
                'rerank_candidates' => (int) env('AGENT_TOOL_RERANK_CANDIDATES', 12),
            ],
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
        'history_messages' => (int) env('AGENT_HISTORY_MESSAGES', 3),
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
            'form_description' => 'mistral-small-3.2-24b-instruct-2506',
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

            // Small "middle model" that routes a turn to the relevant connector(s)
            // for the experimental tool connector_classifier (off by default).
            'connector_router' => 'mistral-small-3.2-24b-instruct-2506',
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
    | Hybrid-search tuning and the vchordrq build/probe parameters.
    | NOTE: KnowledgeBase fetches the whole `chatvia.vector` array.
    |
    */

    'vector' => [
        'search' => [
            // Search accuracy mode: 'default', 'high_accuracy', or 'high_speed'
            'mode' => env('VECTOR_SEARCH_MODE', 'default'),

            // Rerank is the relevance gate: chunks scoring below
            // relevance_threshold are dropped. Turning this off means every query
            // injects the top max_chunks regardless of how well they match —
            // greetings and acknowledgments will pull in noise.
            'rerank' => env('VECTOR_SEARCH_RERANK', true),

            'relevance_threshold' => env('VECTOR_SEARCH_RELEVANCE_THRESHOLD', 0.50),

            'max_chunks' => env('VECTOR_SEARCH_MAX_CHUNKS', 3),

            // Hybrid SQL normally runs in ~200ms; when the phase exceeds this
            // (cold index / autovacuum / checkpoint) KnowledgeBase logs a
            // warning with the full phase breakdown — independent of LOG_LEVEL,
            // so spikes are catchable in prod. Set to 0 to disable.
            'slow_log_ms' => (int) env('VECTOR_SEARCH_SLOW_LOG_MS', 2000),

            // Size of the candidate pool fed to the reranker. Must stay
            // comfortably above max_chunks — the reranker's value is promoting a
            // chunk the rough first-stage RRF under-ranked, so a pool == max_chunks
            // makes it a no-op reorder. Latency is dominated by the rerank
            // round-trip, not the doc count, so trim only if debug rerank_ms
            // justifies it. Ignored when rerank is off (pool is max_chunks * 2).
            'rerank_candidates' => env('VECTOR_SEARCH_RERANK_CANDIDATES', 12),

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
    | Security audit (Enterprise Security & Compliance)
    |--------------------------------------------------------------------------
    |
    | The workspace-scoped security audit log records an immutable row in the
    | dedicated security_audit_log table for auth, membership, configuration,
    | and data operations. A structural clone of the governance tool-call audit:
    | on by default, silent, metadata redacted + size-capped before storage and
    | hashed (sha256) over the RAW payload for tamper-evidence.
    |
    */

    'security_audit' => [
        // Master switch. When false, RecordSecurityEvent drops the event before
        // it touches the DB (CI, local debugging).
        'enabled' => env('SECURITY_AUDIT_ENABLED', true),

        // Default retention (days) for security_audit_log rows. A workspace may
        // SHORTEN this via WorkspaceSecurityPolicy.retention_audit_days but not
        // below `retention_floor_days`; the daily app:prune-security-audit-log
        // sweep enforces both.
        'retention_days' => (int) env('SECURITY_AUDIT_RETENTION_DAYS', 365),

        // Hard floor (days) for per-workspace retention overrides — some regimes
        // mandate a minimum audit window. Overrides clamp up to this.
        'retention_floor_days' => (int) env('SECURITY_AUDIT_RETENTION_FLOOR_DAYS', 30),

        // Size cap (bytes) for the stored metadata. The full raw metadata is
        // hashed (sha256) BEFORE truncation, for integrity.
        'max_metadata_bytes' => (int) env('SECURITY_AUDIT_MAX_METADATA_BYTES', 8192),

        // Metadata keys whose values are replaced with "[redacted]" before the
        // audit row is written (defends against secrets/PII echoed in payloads).
        'redact_keys' => ['password', 'token', 'secret', 'authorization', 'api_key', 'apikey', 'access_token', 'webhook_secret'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention (Enterprise Security & Compliance)
    |--------------------------------------------------------------------------
    |
    | The plan's capabilities->retentionDays is the conversation-history CEILING.
    | An Enterprise workspace may SHORTEN it via
    | WorkspaceSecurityPolicy.retention_conversations_days (never exceed the plan)
    | but not below this floor; the daily app:prune-expired-conversations sweep
    | enforces the override. Audit-log retention lives under `security_audit`.
    |
    */

    'retention' => [
        'conversations_floor_days' => (int) env('CONVERSATION_RETENTION_FLOOR_DAYS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | SIEM webhook (Enterprise — advanced compliance)
    |--------------------------------------------------------------------------
    |
    | Best-effort fan-out of the security-audit stream to a workspace's SIEM
    | endpoint. Delivered (HMAC-signed) by the queued SendSecurityEventToSiem
    | listener; after `max_failures` consecutive failures the webhook auto-
    | disables so a dead endpoint stops generating retried jobs.
    |
    */

    'siem' => [
        'max_failures' => (int) env('SIEM_WEBHOOK_MAX_FAILURES', 15),
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
