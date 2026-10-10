<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Chatvia
|--------------------------------------------------------------------------
|
| Single home for Chatvia's app-specific configuration. Each top-level key
| below is a bounded area (agent, ai, vector, analytics, sse, hmac, widget),
| kept in one file so there's one place to look. Reference values with the
| `chatvia.` prefix, e.g. config('chatvia.ai.provider'),
| config('chatvia.vector.search.mode').
|
*/
return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | Everything that names or links to the product, so a self-hosted install
    | can show its own name and links instead of Chatvia's. Leave a URL empty
    | to hide the link that uses it.
    |
    | - name: product name in the UI, emails, file names and outbound headers.
    | - website_url / docs_url / api_docs_url: links shown in the app.
    | - powered_by: the "Powered by …" credit under hosted chats and widgets.
    |
    */

    'brand' => [
        'name' => env('BRAND_NAME', env('APP_NAME', 'Chatvia')),
        'website_url' => env('BRAND_WEBSITE_URL'),
        'docs_url' => env('BRAND_DOCS_URL'),
        'api_docs_url' => env('BRAND_API_DOCS_URL'),
        'powered_by' => [
            'enabled' => (bool) env('BRAND_POWERED_BY_ENABLED', true),
            'label' => env('BRAND_POWERED_BY_LABEL', 'Chatvia'),
            'url' => env('BRAND_POWERED_BY_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sign-up and message limits
    |--------------------------------------------------------------------------
    |
    | Set in the admin panel (Settings → Workspaces), not here. When the app
    | boots, App\Admin\Settings\SettingsStore puts the saved values into
    | these keys, or the panel's defaults while nothing is saved. The nulls in
    | this file are only placeholders.
    |
    | `registration_enabled` opens the sign-up form at /register.
    | `message_limits.new_workspace` is the monthly limit on agent replies a
    | new workspace starts with (0 means no limit). A platform admin changes
    | the limit of one workspace on its page in the admin panel.
    |
    */

    'registration_enabled' => null,

    'message_limits' => [
        'new_workspace' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo seed
    |--------------------------------------------------------------------------
    |
    | Login for the demo admin account that `php artisan db:seed` creates on a
    | local or staging database. The demo seeders never run in production.
    |
    */

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        'admin_password' => env('SEED_ADMIN_PASSWORD', 'password'),
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
                // document is sent to the document LLM for Q&A, so this bounds
                // cost and latency. Enforced at upload via MaxPdfPages.
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
        // Where chat calls go: scaleway, mistral, openrouter or groq
        // (LlmProviderEnum). Any other value falls back to openrouter.
        'provider' => env('LLM_PROVIDER', 'scaleway'),

        // Which service handles each ingestion and retrieval step. `auto` picks
        // the hosted service when its API key is set, and the local tool (or
        // "off") when it is not — so an install works without those accounts.
        // See docs/guide/self-hosting/ai-providers.md.
        //
        // - document_parser: reads PDFs, office files and images.
        //   mistral (hosted OCR, also reads images) | local (pdftotext +
        //   LibreOffice, text only). auto = mistral when MISTRAL_API_KEY is set.
        // - text_splitter: splits text files, ebooks and web pages into chunks.
        //   chunkfit (hosted) | local. auto = chunkfit when CHUNK_FIT_API_KEY is set.
        // - reranker: scores search results and tools against the question.
        //   qwen | cohere | none. auto = the embedding provider's own reranker
        //   (qwen → qwen, cohere → cohere, jina and mistral → none).
        // - transcriber: speech-to-text for voice input and audio sources.
        //   scaleway | none. auto = scaleway when SCALEWAYAI_API_KEY is set.
        // - document_llm: the LLM that reads whole PDFs, for document summaries
        //   and chat with PDF files. mistral | none. auto = mistral when
        //   MISTRAL_API_KEY is set. With none, those two features are off.
        'document_parser' => env('AI_DOCUMENT_PARSER', 'auto'),
        'text_splitter' => env('AI_TEXT_SPLITTER', 'auto'),
        'reranker' => env('AI_RERANKER', 'auto'),
        'transcriber' => env('AI_TRANSCRIBER', 'auto'),
        'document_llm' => env('AI_DOCUMENT_LLM', 'auto'),

        // Chunk size for the local text splitter, in tokens. Close to the size
        // the hosted splitter produces, so search quality stays similar.
        'local_splitter' => [
            'chunk_tokens' => 400,
            'overlap_tokens' => 0,
        ],

        // https://www.scaleway.com/en/docs/generative-apis/reference-content/supported-models/
        'models' => [
            'vision' => 'gemma-3-27b-it',
            // Embedding provider: qwen (Qwen3 on Scaleway), cohere, jina or
            // mistral. The database stores 1024-dimension vectors, so the
            // provider must return exactly 1024 dimensions.
            'embedding' => env('AI_EMBEDDING_PROVIDER', 'qwen'),

            // Read whole PDF documents through the document_llm above.
            'summary' => env('AI_SUMMARY_MODEL', 'mistral-large-latest'),
            'analysis' => env('AI_ANALYSIS_MODEL', 'mistral-large-latest'),
            // Answers questions about PDF files attached to a chat. Needs both
            // PDF reading and function calling so tools still work in PDF chats.
            'document_chat' => env('AI_DOCUMENT_CHAT_MODEL', env('MISTRAL_QA_MODEL', 'mistral-medium-latest')),

            'tables' => 'mistral-small-3.2-24b-instruct-2506',
            'chunker' => 'llama-3.1-70b-instruct',
            'title' => 'mistral-small-3.2-24b-instruct-2506',
            // Suggests a chat-address slug for an agent (the "generate" button).
            'agent_slug' => 'mistral-small-3.2-24b-instruct-2506',
            'form_description' => 'mistral-small-3.2-24b-instruct-2506',
            'tone' => 'mistral-small-3.2-24b-instruct-2506',
            'topic' => 'mistral-small-3.2-24b-instruct-2506',
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

            // Localizes the agent's three appearance strings (chat title /
            // welcome message / composer placeholder) into every supported
            // language in one structured-JSON call when the owner saves them.
            'translator' => 'mistral-small-3.2-24b-instruct-2506',

            // Suggests conversation starters from a sample of the agent's
            // knowledge-base content (Setup → Starters → "Generate with AI").
            'starters' => 'mistral-small-3.2-24b-instruct-2506',
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
            // Per-million-token prices used for the stored ingestion cost.
            'embedding_per_million_tokens' => 0.10,
            'summary_input_per_million_tokens' => 2.0,
            'summary_output_per_million_tokens' => 8.0,
        ],

        'chunker' => [
            // An extra AI step per chunk (a summary and likely questions,
            // then a new embedding) for uploaded files and crawled pages.
            // One model call per chunk, so it is off by default.
            'enhance' => env('CHUNKER_ENHANCE', false),
            // The same step for text sources. Pasted texts are short, so it
            // costs little and is on by default. Turn both off to ingest
            // without any model calls.
            'enhance_text' => env('CHUNKER_ENHANCE_TEXT', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding storage
    |--------------------------------------------------------------------------
    |
    | How the database stores embeddings (Domain\Knowledge\Embedding\Services\
    | EmbeddingStorage). Set these before the first migration and never change
    | them afterwards: the embedding columns and their indexes are created with
    | them, and `php artisan app:check-embedding-storage` stops a start whose
    | values no longer match the database.
    |
    */

    'embeddings' => [
        // halfvec: 2 bytes per dimension, half the space of vector (4 bytes),
        // with almost the same search quality.
        'type' => env('EMBEDDING_VECTOR_TYPE', 'halfvec'),

        // The embedding provider must return this many dimensions. 1024 works
        // with every provider; Mistral only returns 1024, Jina at most 1024,
        // Cohere 256, 512, 1024 or 1536, and Qwen3 up to 4096.
        'dimensions' => env('EMBEDDING_DIMENSIONS', 1024),
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

            // Knowledge search combines meaning (vector) and keyword (BM25)
            // search; off means meaning only. Set in the admin panel
            // (Settings → Knowledge → Knowledge search), on by default. Keyword
            // data is always stored, so it can change at any time.
            'hybrid' => null,

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
        // once, `scan_age_hours` after creation, over its full transcript. A run
        // that saves nothing (unusable model answer, provider error) is retried
        // by the next pass, at most `max_attempts` runs in all.
        // Emergent topics surface once they recur across `min_occurrences`
        // conversations; `max_examples` caps the examples returned per label.
        'analysis' => [
            'scan_age_hours' => env('ANALYSIS_SCAN_AGE_HOURS', 6),
            'max_attempts' => 5,
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
    | Data retention
    |--------------------------------------------------------------------------
    |
    | `conversations_days` is how long conversations are kept. It is set in
    | the admin panel (Settings → Workspaces); the null here is a placeholder.
    | A workspace may SHORTEN it via
    | WorkspaceSecurityPolicy.retention_conversations_days (never longer) but
    | not below the floor; the daily app:prune-expired-conversations sweep
    | enforces the override. Audit-log retention lives under `security_audit`.
    |
    */

    'retention' => [
        'conversations_days' => null,
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
    | Agent evaluation (eval suites)
    |--------------------------------------------------------------------------
    |
    | Per-agent eval suites run each test case through the agent's REAL pipeline
    | (system prompt + RAG + tools) off-request and score it. Runs are queued so
    | a suite never blocks a chat reply.
    |
    */

    'evals' => [
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
    | The request-id idempotency store. Streams keyed by a client request_id are
    | remembered in `dedupe.store` for `ttl_seconds`; a replay with the same id
    | re-streams the snapshot instead of re-running the LLM.
    |
    */

    'sse' => [
        'dedupe' => [
            // Cache driver name from config/cache.php; null = default store.
            'store' => env('SSE_DEDUPE_STORE'),
            'ttl_seconds' => (int) env('SSE_DEDUPE_TTL', 600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget (embeddable chat)
    |--------------------------------------------------------------------------
    |
    | The embed script URL and the language picker — which
    | reads the shared `chatvia.languages` list above (no duplicate copy).
    |
    */

    'widget' => [
        // The widget's embed.js. By default the copy this server builds from
        // shakomako/ into public/shakomako (`npm run shakomako:build`). Set
        // WIDGET_SCRIPT_URL to load it from elsewhere, e.g. the widget dev
        // server (http://localhost:3000/dist/embed.js) or a CDN.
        'script_url_embed' => env('WIDGET_SCRIPT_URL') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/shakomako/embed.js',

        // Public address of the Reverb (WebSocket) server for live-agent
        // handoff, sent to the widget with its settings, so a widget build
        // needs no Reverb settings of its own. The same values the dashboard
        // is built with. Empty values mean "the server the widget API is on"
        // (the Docker image forwards /app/ to Reverb).
        'reverb' => [
            'host' => env('VITE_REVERB_HOST'),
            'port' => env('VITE_REVERB_PORT'),
            'scheme' => env('VITE_REVERB_SCHEME'),
        ],

        // Hosts every agent's widget may load on, on top of each agent's own
        // allowed sites. Always this install's own host (from APP_URL), where
        // the dashboard playground and hosted chat embed the widget, plus any
        // extra hosts in WIDGET_ALWAYS_ALLOWED_ORIGINS (comma-separated, e.g.
        // "example.com,app.example.com"; empty by default), so an install
        // never trusts an outside domain unless told to.
        'always_allowed_origins' => array_values(array_unique(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            [
                (string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST),
                ...explode(',', (string) env('WIDGET_ALWAYS_ALLOWED_ORIGINS', '')),
            ],
        )))),

        // Global per-agent ceilings for the LLM-cost widget stream, keyed on the
        // server-resolved agent so a client cannot multiply its budget (and burn
        // the owner's quota/spend) by rotating X-SESSION-ID or source IPs.
        'stream_per_agent_per_minute' => (int) env('WIDGET_STREAM_PER_AGENT_PER_MINUTE', 120),
        'stream_per_agent_per_hour' => (int) env('WIDGET_STREAM_PER_AGENT_PER_HOUR', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security headers
    |--------------------------------------------------------------------------
    |
    | `csp` controls the ContentSecurityPolicy middleware:
    |   - off          send no security headers at all
    |   - report-only  send the policy as Content-Security-Policy-Report-Only:
    |                  browsers only report what it would block, nothing breaks
    |   - enforce      send Content-Security-Policy, plus X-Frame-Options and
    |                  (production over HTTPS) HSTS
    | `csp_report_uri` is an optional endpoint that receives the violation
    | reports (the `report-uri` directive).
    |
    */

    'security' => [
        'csp' => env('CSP_MODE', 'report-only'),
        'csp_report_uri' => env('CSP_REPORT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unfinished features
    |--------------------------------------------------------------------------
    |
    | Install-wide switches for features that are not finished yet. While a
    | switch is off, the feature's routes answer 404 (EnsureFeatureFlagIsOn)
    | and the dashboard hides its entry points (shared as `features`).
    |
    | - youtube_sources: add YouTube videos as knowledge sources. Added
    |   videos are saved but not ingested yet.
    |
    */

    'features' => [
        'youtube_sources' => (bool) env('FEATURE_YOUTUBE_SOURCES', false),
    ],

];
