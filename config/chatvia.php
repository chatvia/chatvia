<?php

declare(strict_types=1);
use Domain\Conversation\Services\ConversationRetention;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;

/*
|--------------------------------------------------------------------------
| Chatvia
|--------------------------------------------------------------------------
|
| Single home for Chatvia's app-specific configuration. Each top-level key
| below is a bounded area (agent, ai, vector, analytics, sse, hmac, widget),
| kept in one file so there's one place to look. Reference values with the
| `chatvia.` prefix, e.g. config('chatvia.ai.reranker'),
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
    | to hide the link that uses it. Set in the admin panel (Settings → Brand);
    | the values here are the defaults while nothing is saved there.
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
    | Set in the admin panel (Settings → Workspaces). The values here are the
    | defaults while nothing is saved there.
    |
    | `registration_enabled` opens the sign-up form at /register.
    | `message_limits.new_workspace` is the monthly limit on agent replies a
    | new workspace starts with (0 means no limit). A platform admin changes
    | the limit of one workspace on its page in the admin panel.
    |
    */

    'registration_enabled' => true,

    'message_limits' => [
        'new_workspace' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | File storage
    |--------------------------------------------------------------------------
    |
    | Where the `private` disk (knowledge files, backups) and the `public` disk
    | (chat attachments, chunk images) keep their files: on this server, or in
    | S3-compatible object storage (AWS S3, Scaleway, Cloudflare R2, MinIO,
    | ...). Set in the admin panel (Settings → Storage); the values below are
    | the defaults while nothing is saved there, so an install configured in
    | .env keeps working. App\Admin\Settings\StorageDisks builds the two
    | disks from this.
    |
    */

    'storage' => [
        'driver' => env('FILESYSTEM_PRIVATE_DRIVER', filled(env('SCALEWAY_BUCKET')) ? 's3' : 'local'),
        's3' => [
            'key' => env('SCALEWAY_ACCESS_KEY_ID'),
            'secret' => env('SCALEWAY_SECRET_ACCESS_KEY'),
            'region' => env('SCALEWAY_DEFAULT_REGION'),
            'endpoint' => env('SCALEWAY_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('SCALEWAY_USE_PATH_STYLE_ENDPOINT', false),
            'private_bucket' => env('SCALEWAY_BUCKET'),
            'private_url' => env('SCALEWAY_URL'),
            'public_bucket' => env('SCALEWAY_BUCKET_PUBLIC'),
            'public_url' => env('SCALEWAY_PUBLIC_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin setup
    |--------------------------------------------------------------------------
    |
    | How the setup of a new install ended: `completed` or `skipped` (AI,
    | models, documents, storage and email). While it is empty, every admin
    | panel page leads to the setup; a skipped one can be finished later from
    | Settings. Saved by the admin panel (PlatformSettings), never in .env.
    |
    */

    'admin' => [
        'setup_status' => null,
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
                // The largest PDF a chatter may attach (25 MB).
                'max_file_size_in_kb' => 25600,
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

                // The reranker score (0..1) a tool must reach to ship. Each
                // reranking model scores on its own scale, so null (the
                // default) uses the model's measured thresholds
                // (RerankModelCatalog). A wrong READ is cheap (the user re-asks);
                // a wrong WRITE executes, so writes can get a stricter bar. Set
                // these only to try other values against the offline eval
                // (tests/Eval/ToolRoutingEvalTest). See ToolRelevanceGate.
                'relevance_threshold_read' => env('AGENT_TOOL_RELEVANCE_THRESHOLD_READ'),
                'relevance_threshold_write' => env('AGENT_TOOL_RELEVANCE_THRESHOLD_WRITE'),

                // Without a reranker (or when it fails), tools are gated by the
                // similarity of their embedding to the message instead: the
                // bars of the embedding model in use (EmbeddingProvider), or
                // these when set. A model that was never measured gets no gate.
                'similarity_threshold_read' => env('AGENT_TOOL_SIMILARITY_THRESHOLD_READ'),
                'similarity_threshold_write' => env('AGENT_TOOL_SIMILARITY_THRESHOLD_WRITE'),

                // EXPERIMENTAL (off by default). A small "middle model" connector
                // router: after the relevance gate, a cheap structured LLM call
                // (the Fast model, Settings → AI models) picks which
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

        // The chat model (LlmRole::Chat): the catalog model, by its alias
        // (`llms.external_name`), that new agents start with and the
        // analytics assistant uses. Set in the admin panel (Settings → AI models).
        // Unset, or not usable, means the first usable model of the catalog.
        'default_model' => null,

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
        // The catalog models (aliases, `llms.external_name`) behind the app's
        // own LLM calls, picked in the admin panel (Settings → AI models). A role
        // that is unset uses the chat model (chatvia.agent.default_model).
        // Every call goes to its model's provider. See LlmRole.
        'roles' => [
            'fast' => null,
            'smart' => null,
            'vision' => null,
        ],

        // Which service handles each ingestion and retrieval step (Settings →
        // AI models). `auto` picks the first provider whose key is set in
        // Settings → AI providers, and "off" when none is — so an install
        // works without those accounts. See
        // https://chatvia.ai/docs/guide/self-hosting/ai-providers.
        //
        // - document_parser: reads PDFs, Word, slides and images. mistral
        //   (Mistral OCR) | azure (Azure Document Intelligence) | none (those
        //   files cannot be uploaded; spreadsheets and CSV are still read by
        //   the app). auto = mistral, else azure, when connected.
        // - reranker: scores search results and tools against the question
        //   (a cross-encoder). openrouter | cohere | custom (a self-hosted
        //   /rerank server) | none (a basic similarity check instead). auto =
        //   openrouter, else cohere, when connected, else none.
        // - transcriber: speech-to-text for voice input and audio sources.
        //   scaleway | openai (both Whisper) | none. auto = scaleway, else
        //   openai, when connected.
        // - document_llm: the LLM that reads whole PDFs, for document summaries
        //   and chat with PDF files. mistral | none. auto = mistral when it is
        //   connected. With none, those two features are off.
        'document_parser' => env('AI_DOCUMENT_PARSER', 'auto'),
        'reranker' => env('AI_RERANKER', 'auto'),

        // The reranker's model and a self-hosted reranker's address (Settings
        // → AI). The models to choose from: RerankModelCatalog.
        'rerank' => [
            'openrouter_model' => 'cohere/rerank-4-pro',
            'custom' => [
                'url' => null,
                'key' => null,
                'model' => null,
            ],
            'timeout' => 10,
        ],
        'transcriber' => env('AI_TRANSCRIBER', 'auto'),
        'document_llm' => env('AI_DOCUMENT_LLM', 'auto'),

        // The tokenizer (a tiktoken encoding) every token count uses: chunk
        // sizes in tokens, the stored size of each chunk, estimates. Set in
        // the admin panel (Settings → Knowledge).
        'tokenizer' => 'cl100k_base',

        // How text is split into chunks (Settings → Knowledge); these are the
        // defaults. The size and overlap are in the unit: tokens (of the
        // tokenizer above) or characters. A null size is the unit's default,
        // 400 tokens or 1,600 characters. The strategy for each kind of
        // content: Domain\Knowledge\Ingestion\Enums\ContentKind and SplitStrategy.
        'chunking' => [
            'unit' => 'tokens',
            'size' => null,
            'overlap' => 0,
            'strategies' => [
                'markdown' => 'markdown',
                'text' => 'recursive',
                'transcript' => 'sentence',
            ],
        ],

        'models' => [
            // Embedding provider: qwen (Qwen3 on Scaleway), openai, google
            // (Gemini), cohere, mistral or jina. The database stores 1024-dimension vectors, so the
            // provider must return exactly 1024 dimensions.
            'embedding' => env('AI_EMBEDDING_PROVIDER', 'qwen'),

            // Read whole PDF documents through the document_llm above. These
            // are model names of that provider, not catalog models.
            'summary' => env('AI_SUMMARY_MODEL', 'mistral-large-latest'),
            'analysis' => env('AI_ANALYSIS_MODEL', 'mistral-large-latest'),
            // Answers questions about PDF files attached to a chat. Needs both
            // PDF reading and function calling so tools still work in PDF chats.
            'document_chat' => env('AI_DOCUMENT_CHAT_MODEL', env('MISTRAL_QA_MODEL', 'mistral-medium-latest')),
        ],

        'query' => [
            'rewriter' => [
                // Turns a follow-up ("and the price?") into a full question for
                // the knowledge search, from the chat before it. One Fast-model
                // call per follow-up; the first message of a chat needs none.
                'enabled' => env('QUERY_REWRITER_ENABLED', true),
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
            // then a new embedding) for uploaded files and crawled pages, and
            // a summary of the whole source. It finds far more of the right
            // passages ("contextual retrieval"), so it is on by default; it
            // costs one Smart-model call per chunk while training.
            'enhance' => env('CHUNKER_ENHANCE', true),
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
        // Cohere 256, 512, 1024 or 1536, OpenAI up to 1536 (3072 with
        // text-embedding-3-large), Gemini 128 to 3072, Qwen3 up to 4096, and
        // OpenRouter what its model returns.
        'dimensions' => env('EMBEDDING_DIMENSIONS', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge
    |--------------------------------------------------------------------------
    |
    | The file types workspaces may upload as knowledge sources, as
    | KnowledgeFileType values (PDF, Word, images, audio…). Set in the admin
    | panel (Settings → Knowledge); every type is on by default. Files already
    | uploaded keep working when their type is turned off.
    |
    */

    'knowledge' => [
        'file_types' => array_column(KnowledgeFileType::cases(), 'value'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector search (VectorChord / RAG)
    |--------------------------------------------------------------------------
    |
    | Hybrid-search tuning and the vchordrq build/probe parameters.
    | NOTE: HybridChunkSearch reads the whole `chatvia.vector` array.
    |
    */

    'vector' => [
        'search' => [
            // Search accuracy mode: 'default', 'high_accuracy', or 'high_speed'
            'mode' => env('VECTOR_SEARCH_MODE', 'default'),

            // Knowledge search combines meaning (vector) and keyword (BM25)
            // search; off means meaning only. Set in the admin panel
            // (Settings → Knowledge), on by default. Keyword data is always
            // stored, so it can change at any time.
            'hybrid' => true,

            // The relevance check ("Leave out weak matches"): with a reranker
            // (Settings → AI models), chunks it scores below
            // relevance_threshold are dropped; without one (or when it fails),
            // chunks whose meaning is too far from the question
            // (similarity_threshold). So greetings pull in nothing. Off means
            // every question gets the top max_chunks, however well they match.
            'relevance_check' => env('VECTOR_SEARCH_RELEVANCE_CHECK', true),

            // The reranker score (0..1) a chunk must reach. Null (the default)
            // uses the reranking model's measured threshold (RerankModelCatalog).
            'relevance_threshold' => env('VECTOR_SEARCH_RELEVANCE_THRESHOLD'),

            // Without a reranker: the cosine similarity a chunk must reach.
            // Null uses the embedding model's measured value
            // (EmbeddingProvider); a model never measured gets no check.
            'similarity_threshold' => env('VECTOR_SEARCH_SIMILARITY_THRESHOLD'),

            // The cosine similarity a message must reach to a saved Q&A
            // question to get its answer by meaning (a whole-question match
            // needs none). Null uses the embedding model's measured value; a
            // model never measured matches Q&A only as whole questions.
            'qa_similarity_threshold' => env('VECTOR_SEARCH_QA_SIMILARITY_THRESHOLD'),

            'max_chunks' => env('VECTOR_SEARCH_MAX_CHUNKS', 5),

            // The most tokens of knowledge one question may add to the prompt:
            // lower-ranked chunks past it are left out (the best one always
            // goes in).
            'max_context_tokens' => env('VECTOR_SEARCH_MAX_CONTEXT_TOKENS', 3000),

            // Hybrid SQL normally runs in ~200ms; when the phase exceeds this
            // (cold index / autovacuum / checkpoint) KnowledgeBase logs a
            // warning with the full phase breakdown — independent of LOG_LEVEL,
            // so spikes are catchable in prod. Set to 0 to disable.
            'slow_log_ms' => 2000,

            // Size of the candidate pool fed to the reranker. Must stay
            // comfortably above max_chunks — the reranker's value is promoting a
            // chunk the rough first-stage RRF under-ranked, so a pool == max_chunks
            // makes it a no-op reorder. Latency is dominated by the rerank
            // round-trip, not the doc count, so trim only if debug rerank_ms
            // justifies it. Ignored when rerank is off (pool is max_chunks * 2).
            'rerank_candidates' => env('VECTOR_SEARCH_RERANK_CANDIDATES', 25),

            // Agents that reach at most this many chunks are searched exactly:
            // every one of their chunks is scored, with no approximate index and
            // no other workspace's rows involved (full recall). Larger agents go
            // through the vector and BM25 indexes. 0 always uses the indexes.
            'exact_max_chunks' => 10_000,

            // vchordrq.probes, as a percentage of the index's partitions
            // ("lists"). The vector indexes start flat (no partitions, no probes)
            // and vector:reindex partitions them once chunks pass 100k rows,
            // storing the default value on each index. Higher = better recall on
            // the indexed path, slower search.
            'probes_percent' => [
                'default' => 3,
                'high_accuracy' => 6,
                'high_speed' => 1.5,
            ],

            // vchordrq.epsilon — early-stop radius for the residual-quantized search.
            'epsilon' => [
                'default' => 1.9,
                'high_accuracy' => 2.5,
                'high_speed' => 1.4,
            ],
        ],

        // Threads used by vector:reindex (vchordrq build_threads). nproc is too
        // fragile to call from PHP, so pin it per environment.
        'build_threads' => env('VECTORCHORD_BUILD_THREADS', 4),

        // Memory vector:reindex lets one k-means build use, in MB. Larger
        // indexes sample fewer vectors per partition to stay inside it.
        'build_memory_mb' => 2048,
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
        'max_arg_bytes' => 4096,
        'max_result_bytes' => 8192,

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
        'max_metadata_bytes' => 8192,

        // Metadata keys whose values are replaced with "[redacted]" before the
        // audit row is written (defends against secrets/PII echoed in payloads).
        'redact_keys' => ['password', 'token', 'secret', 'authorization', 'api_key', 'apikey', 'access_token', 'webhook_secret'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    | `conversations_days` is how long conversations are kept. Both values are
    | set in the admin panel (Settings → Workspaces); these are the defaults.
    | A workspace may SHORTEN it via
    | WorkspaceSecurityPolicy.retention_conversations_days (never longer) but
    | not below the floor; the daily app:prune-expired-conversations sweep
    | enforces the override. Audit-log retention lives under `security_audit`.
    |
    */

    'retention' => [
        'conversations_days' => ConversationRetention::DEFAULT_DAYS,
        'conversations_floor_days' => (int) env('CONVERSATION_RETENTION_FLOOR_DAYS', 1),

        // Optional windows a workspace sets in Settings → Security; nothing is
        // deleted while one is blank. Daily jobs enforce them:
        // app:prune-leads, app:prune-workspace-analytics and
        // app:anonymize-chat-sessions. Analytics keep at least a month so the
        // charts stay useful. "Anonymize after" may not be longer than the
        // conversation retention (the chats are gone by then).
        'leads_floor_days' => 1,
        'analytics_floor_days' => 30,
        'anonymize_floor_days' => 1,
        // The longest leads or analytics window a workspace may set (ten years).
        'max_days' => 3650,
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
        'max_output_bytes' => 8192,
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
        'max_snippet_bytes' => 2048,

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
            'ttl_seconds' => 600,
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
        // The widget's embed.js: a path on this server or a full address. By
        // default the copy this server builds into public/shakomako
        // (`npm run shakomako:build`). The admin panel (Settings → Platform)
        // can point it elsewhere, e.g. the widget dev server
        // (http://localhost:3000/dist/embed.js) or a CDN. Read it through
        // url(), which turns a path into a full address.
        'script_url_embed' => '/shakomako/embed.js',

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
        // the dashboard playground and hosted chat embed the widget, plus the
        // extra hosts set in the admin panel (Settings → Platform), a
        // comma-separated list such as "example.com,app.example.com", empty by
        // default. An install never trusts an outside domain unless told to.
        'always_allowed_origins' => array_values(array_filter([
            strtolower((string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST)),
        ])),
        'extra_allowed_origins' => env('WIDGET_ALWAYS_ALLOWED_ORIGINS', ''),

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

        // Which public forms ask for the Cloudflare Turnstile bot check once
        // its keys (services.turnstile) are saved. Set in the admin panel
        // (Settings → Security). See App\Http\Auth\BotCheck.
        'turnstile' => [
            'sign_up' => true,
            'sign_in' => false,
            'password_reset' => true,
        ],
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
