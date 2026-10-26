<?php

declare(strict_types=1);

use Domain\Conversation\Services\ConversationRetention;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Illuminate\Support\Uri;

return [

    /*
    |--------------------------------------------------------------------------
    | Powered By Credit
    |--------------------------------------------------------------------------
    |
    | Whether the "Powered by ChatVia.ai" credit, linked to https://chatvia.ai,
    | shows under hosted chats and widgets. It is switched off in the admin
    | panel (Settings → Branding), and only with a license key (see License
    | below); the value here is the default.
    |
    */

    'powered_by' => true,

    /*
    |--------------------------------------------------------------------------
    | License
    |--------------------------------------------------------------------------
    |
    | A license key allows hiding the "Powered by" credit. chatvia.ai sells
    | and signs keys (Ed25519). A key as bought names no domain: saving it in
    | the admin panel (Settings → Branding) activates it at chatvia.ai for the
    | host of `app.url`, one domain per key, and the install then checks it
    | offline. Once a day the install asks chatvia.ai whether the key is
    | still active there (the buyer can deactivate it to move it): "no"
    | removes the key, no answer keeps it. Without a valid key the credit
    | always shows.
    |
    | An Enterprise key also unlocks the Enterprise security features. It is
    | yearly: chatvia.ai reports it inactive once the subscription ends, and
    | the daily check then removes it. `enterprise` is worked out from the
    | key by SettingsConfig; setting it here has no effect.
    |
    | `public_key` checks the keys. It is fixed here, never in .env or the
    | admin panel, because whoever sets it could sign their own keys. While
    | it is empty, no key is valid. `buy_url`, `manage_url` and `api_url`
    | are on ChatVia.ai's own site: set CHATVIA_URI (no trailing slash) only to
    | test against a local copy of it.
    |
    */

    'license' => [
        'key' => null,
        'enterprise' => false,
        'public_key' => 'Syv1BerjH1Daz/uepuAgSjxf0uyyzbjzKyc2osApnQc=',
        'buy_url' => env('CHATVIA_URI', 'https://chatvia.ai').'/license',
        'manage_url' => env('CHATVIA_URI', 'https://chatvia.ai').'/license/manage',
        'api_url' => env('CHATVIA_URI', 'https://chatvia.ai').'/api/licenses',
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation
    |--------------------------------------------------------------------------
    |
    | The docs on ChatVia.ai's own site. Each settings card links to its part
    | of the admin panel docs, under /guide/admin-panel/settings/.
    |
    */

    'docs_url' => env('CHATVIA_URI', 'https://chatvia.ai').'/docs',

    /*
    |--------------------------------------------------------------------------
    | Tracking Code
    |--------------------------------------------------------------------------
    |
    | HTML, such as the script of an analytics service, added to the <head>
    | of every page in production. Set in the admin panel (Settings →
    | Platform). Nothing is added while it is empty.
    |
    */

    'tracking_code' => null,

    /*
    |--------------------------------------------------------------------------
    | Sign-up And Message Limits
    |--------------------------------------------------------------------------
    |
    | `registration_enabled` opens the sign-up form at /register.
    | `message_limits.new_workspace` is the monthly limit on agent replies a
    | new workspace starts with (0 means no limit); a platform admin changes
    | one workspace's limit on its page. Both are set in the admin panel
    | (Settings → Workspaces); the values here are the defaults.
    |
    */

    'registration_enabled' => true,

    'message_limits' => [
        'new_workspace' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner Account
    |--------------------------------------------------------------------------
    |
    | Whether a workspace owner may download a copy of their data
    | (`data_export`) or delete their account (`deletion`) from the
    | dashboard's Account area. Off hides the page and its routes answer 404;
    | a platform admin can still do both on the workspace's page. Set in the
    | admin panel (Settings → Workspaces); the values here are the defaults.
    |
    */

    'account' => [
        'data_export' => true,
        'deletion' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel
    |--------------------------------------------------------------------------
    |
    | `setup_status` is how the setup of a new install ended: "completed" or
    | "skipped". While it is empty, every admin panel page leads to the
    | setup; a skipped one can be finished later from Settings.
    | `logs_page_enabled` shows the panel's Logs page (Settings → Platform →
    | Development). Both are saved by the admin panel.
    |
    */

    'admin' => [
        'setup_status' => null,
        'logs_page_enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse and Spectra
    |--------------------------------------------------------------------------
    |
    | Whether Pulse (the app's health, /pulse) and Spectra (the AI call log,
    | /spectra) record, and how many days Spectra keeps a call before
    | `spectra:prune` removes it. Saved in Settings → Observability. Both record
    | out of the box; an admin can switch them off there. PULSE_ENABLED and
    | SPECTRA_ENABLED in .env decide whether a tool is part of the install
    | at all.
    |
    */

    'pulse' => [
        'recording' => true,
    ],

    'spectra' => [
        'recording' => true,
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | The automatic backup (chatvia:backup:create, checked every hour): the
    | database and Redis in one archive on the `private` disk under
    | `backups/`, in the layout of `./manage backup`. `frequency` is a
    | BackupFrequency value (6h, 12h, 24h, 48h, week); `keep` is how many
    | backups stay. With an OpenPGP `public_key` the archive is encrypted to
    | it and also holds the app key. `include_files` adds the uploaded files
    | (only with storage on this server); `include_tool_databases` adds the
    | Pulse and Spectra databases. Saved in Settings → Backups.
    |
    */

    'backup' => [
        'enabled' => true,
        'frequency' => '24h',
        'keep' => 14,
        'public_key' => null,
        'include_files' => false,
        'include_tool_databases' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Seed
    |--------------------------------------------------------------------------
    |
    | The login of the demo admin account that `php artisan db:seed` creates
    | on a local or staging database. The demo seeders never run in
    | production.
    |
    */

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        'admin_password' => env('SEED_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | The languages of the widget and hosted chat language pickers, and the
    | allowed values of a user's dashboard language, so every dashboard
    | language (SUPPORTED_LOCALES in resources/js/i18n) must be listed here.
    | EU languages come first, like the dashboard switcher; "auto" (the agent
    | detects the visitor's language) stays first of all.
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
    | `files.upload`: the largest file a chatter may attach (in KB), how many
    | at once, and the most pages of a PDF. The model for chat files reads
    | the whole file, so the page cap bounds cost and time (MaxPdfPages
    | checks it at upload).
    |
    | `tools`: the most tool-call rounds one turn may run, so a model that
    | keeps asking for tools cannot loop forever (after the last round the
    | model answers without tools); the longest tool result the model reads,
    | in characters (a longer one is cut, with a note saying so); the switch
    | for tool approval (off runs every tool at once, whatever a tool's own
    | `requires_approval` says); and how many minutes an approval stays open
    | before it expires. All four are set in the admin panel (Settings →
    | Security → Tool calls).
    |
    | `tools.routing`: which tools one turn gets. The hybrid tool search
    | ranks the candidates, and the relevance gate drops those a reranker
    | scores below the bar, so a greeting ships no tools at all. Off ships
    | the top search hits every time. The bars are on each model's own
    | scale: null uses the measured bars (RerankModelCatalog, and for the
    | similarity check without a reranker, EmbeddingProvider), and writes
    | can get a stricter bar than reads. Set them only to try other values
    | against tests/Eval/ToolRoutingEvalTest. `connector_classifier`
    | (experimental, off) asks the Fast model which connectors the turn is
    | about and drops write tools of the others; it costs one LLM call per
    | turn with tools. `rerank_candidates` is how many search hits go to the
    | reranker.
    |
    | `default_model` is the chat model (an LLM catalog alias) new agents
    | start with and the analytics assistant uses; unset, or not usable,
    | means the first usable model. `history_messages` is how many recent
    | messages each turn replays to the model. Both are set in the admin
    | panel (Settings → AI models).
    |
    */

    'agent' => [
        'files' => [
            'upload' => [
                'max_file_size_in_kb' => 25600,
                'max_files' => 1,
                'max_pdf_pages' => 10,
            ],
        ],

        'tools' => [
            'max_iterations' => 5,
            'max_result_chars' => 64000,
            'approval_enabled' => true,
            'approval_ttl_minutes' => 1440,

            'routing' => [
                'relevance_gate' => (bool) env('AGENT_TOOL_RELEVANCE_GATE', true),
                'relevance_threshold_read' => env('AGENT_TOOL_RELEVANCE_THRESHOLD_READ'),
                'relevance_threshold_write' => env('AGENT_TOOL_RELEVANCE_THRESHOLD_WRITE'),
                'similarity_threshold_read' => env('AGENT_TOOL_SIMILARITY_THRESHOLD_READ'),
                'similarity_threshold_write' => env('AGENT_TOOL_SIMILARITY_THRESHOLD_WRITE'),
                'connector_classifier' => (bool) env('AGENT_TOOL_CONNECTOR_CLASSIFIER', false),
                'rerank_candidates' => (int) env('AGENT_TOOL_RERANK_CANDIDATES', 12),
            ],
        ],

        'default_model' => null,

        'history_messages' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Services
    |--------------------------------------------------------------------------
    |
    | Everything here is set in the admin panel (Settings → AI models,
    | Knowledge and Search); the values are the defaults.
    |
    | `tasks`: the catalog model (alias) behind each of the app's own LLM
    | calls (LlmTask). An unset task uses `default`, then the chat model.
    |
    | The service of each step; "auto" picks the first one whose key is set
    | in Settings → AI providers, and "none" when there is none:
    |
    | - document_parser: reads PDFs, Word, slides and images. "mistral",
    |   "azure" or "none" (those files cannot be uploaded).
    | - reranker: scores search results and tools against the question.
    |   "openrouter", "cohere", "custom" (a self-hosted /rerank server) or
    |   "none" (a similarity check instead). `rerank` holds its model, the
    |   custom server and the timeout in seconds (RerankModelCatalog).
    | - transcriber: speech to text for voice input and audio sources.
    |   "scaleway", "openai" or "none".
    | - voice: live voice calls (the OpenAI Realtime API). "openai" or
    |   "none". Each agent still turns it on.
    | - chat_files: whether chatters may attach a PDF file or an image in a
    |   chat (off: no agent can offer it), and the catalog model (its
    |   `llms.id`) that answers about it when the agent's own model cannot,
    |   one that reads both (models.dev inputs). Null: the default chat model when it reads them, else the
    |   first model that does; none means no uploads.
    | - source_summaries: whether each source gets a summary and an analysis
    |   (type, language, likely questions), read as a whole PDF, and the
    |   catalog model (its `llms.id`) that writes them, one that reads PDF
    |   files (models.dev inputs). Null: the default chat model when it reads
    |   them, else the first model that does; none means no summaries.
    |
    | `tokenizer` is the tiktoken encoding of every token count. `chunking`
    | is how text is split: the unit ("tokens" or "characters"), the size
    | (null is 400 tokens or 1,600 characters), the overlap, the smallest
    | chunk the splitter aims for (null is 128 tokens or 512 characters, 0 is
    | off, at most half the size), and the strategy for each kind of content
    | (ContentKind, SplitStrategy).
    |
    | `models`: the embedding provider ("qwen", "openai", "openrouter",
    | "google", "cohere", "mistral", "jina" or "voyage"; it must return
    | `embeddings.dimensions` dimensions). Null until the admin picks one:
    | then nothing is embedded.
    |
    | `query.rewriter` turns a follow-up ("and the price?") into a full
    | question for the knowledge search: one quick-task model call per follow-up.
    | `query.rewriter.clarify` lets the same call flag a message too vague to
    | search, so the agent asks what the visitor means instead of guessing.
    | `chunker.enhance` adds an AI step per chunk (a summary and likely
    | questions, then a new embedding) for files and web pages, and a
    | summary of the whole source; it finds far more of the right passages
    | and costs one quick-task model call per chunk.
    | `chunker.enhance_text` does the same for text sources.
    | `chunker.describe_images` lets that step describe the pictures inside
    | documents with the Image descriptions model; the setup turns it off
    | when no model that reads images is set up.
    |
    */

    'ai' => [
        'tasks' => [
            'default' => null,
            'follow_up' => null,
            'tool_routing' => null,
            'guardrails' => null,
            'titles' => null,
            'conversation_analysis' => null,
            'starters' => null,
            'translation' => null,
            'form_text' => null,
            'chat_address' => null,
            'enhancement' => null,
            'tables' => null,
            'images' => null,
            'eval_judge' => null,
        ],

        // Whether agents may reason before they answer (Settings → AI models).
        // Off: every agent is Off, whatever effort it picked.
        'reasoning' => [
            'enabled' => false,
        ],

        // Whether agents may let chatters pick the model of each message
        // (Settings → AI models). Off: every agent answers with its own model.
        'chatter_model_choice' => false,

        'document_parser' => 'auto',
        'reranker' => 'auto',
        'rerank' => [
            'openrouter_model' => 'cohere/rerank-4-pro',
            'custom' => [
                'url' => null,
                'key' => null,
                'model' => null,
            ],
            'timeout' => 10,
        ],
        'transcriber' => 'auto',
        'voice' => 'auto',
        'speaker' => 'auto',
        'chat_files' => [
            'enabled' => true,
            'model' => null,
        ],
        'source_summaries' => [
            'enabled' => true,
            'model' => null,
        ],

        'tokenizer' => 'cl100k_base',

        'chunking' => [
            'unit' => 'tokens',
            'size' => null,
            'overlap' => 0,
            'minimum' => null,
            'strategies' => [
                'markdown' => 'markdown',
                'text' => 'recursive',
                'transcript' => 'sentence',
            ],
        ],

        'models' => [
            'embedding' => null,
        ],

        'query' => [
            'rewriter' => [
                'enabled' => true,
                // Let the rewrite flag a message too vague to search (a bare
                // number, one unclear word); when nothing is found, the agent
                // then asks what the visitor means. Off until measured.
                'clarify' => false,
            ],
        ],

        'chunker' => [
            'enhance' => true,
            'enhance_text' => true,
            'describe_images' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Storage
    |--------------------------------------------------------------------------
    |
    | How the database stores embeddings (EmbeddingStorage). Set these before
    | the first migration and never change them: the embedding columns and
    | their indexes are created with them, and `php artisan
    | chatvia:search:check-embedding-storage` stops a start whose values no longer
    | match the database.
    |
    | `type` "halfvec" takes 2 bytes per dimension, half the space of
    | "vector", with almost the same search quality. The embedding provider
    | must return `dimensions` dimensions: 1024 works with every provider.
    |
    | `index` is what the chunk HNSW indexes hold: "binary" (one bit per
    | dimension, about 6× smaller; search re-scores with the stored vectors)
    | or "full". It may change later: the start-up runs `php artisan
    | chatvia:search:vector-indexes --if-changed`, which rebuilds them.
    |
    */

    'embeddings' => [
        'type' => env('EMBEDDING_VECTOR_TYPE', 'halfvec'),
        'dimensions' => env('EMBEDDING_DIMENSIONS', 1024),
        'index' => env('EMBEDDING_INDEX', 'binary'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge File Types
    |--------------------------------------------------------------------------
    |
    | The file types workspaces may upload as knowledge sources, as
    | KnowledgeFileType values. Set in the admin panel (Settings →
    | Knowledge); every type is on by default. Files already uploaded keep
    | working when their type is turned off.
    |
    */

    'knowledge' => [
        'file_types' => array_column(KnowledgeFileType::cases(), 'value'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge Search
    |--------------------------------------------------------------------------
    |
    | `mode` is "default", "high_accuracy" or "high_speed"; it picks the
    | `ef_search` the HNSW vector indexes search with (higher finds more,
    | slower). `hybrid` adds keyword search to meaning (vector) search.
    |
    | `relevance_check` leaves out weak matches: with a reranker, chunks it
    | scores below `relevance_threshold`; without one, chunks whose meaning
    | is too far from the question (`similarity_threshold`). Null uses the
    | model's measured bar. `qa_similarity_threshold` is how close a message
    | must be to a saved Q&A question to get its answer by meaning.
    |
    | `max_chunks` and `max_context_tokens` cap what one question adds to the
    | prompt (the best chunk always goes in). These and the settings above
    | are set in the admin panel (Settings → Search).
    |
    | `rerank_candidates` is how many hits go to the reranker; keep it well
    | above `max_chunks`. Agents with at most `exact_max_chunks` chunks are
    | searched exactly, without the indexes (0 always uses them). With binary
    | indexes, each vector channel takes `binary_rescore` times its candidates
    | from the index and keeps the best by the stored vectors. A search
    | slower than `slow_log_ms` is logged (0 turns it off).
    |
    */

    'vector' => [
        'search' => [
            'mode' => 'default',
            'hybrid' => true,
            'relevance_check' => true,
            'relevance_threshold' => env('VECTOR_SEARCH_RELEVANCE_THRESHOLD'),
            'similarity_threshold' => null,
            'qa_similarity_threshold' => null,
            'max_chunks' => env('VECTOR_SEARCH_MAX_CHUNKS', 5),
            'max_context_tokens' => 3000,
            'slow_log_ms' => 2000,
            'rerank_candidates' => env('VECTOR_SEARCH_RERANK_CANDIDATES', 25),
            'exact_max_chunks' => 10_000,
            'binary_rescore' => 4,
            // When nothing passes the relevance check, tell the model what the
            // agent's knowledge covers (its sources' names), so it can say what
            // it can help with. Off until measured.
            'scope_hint' => false,

            'ef_search' => [
                'default' => 100,
                'high_accuracy' => 300,
                'high_speed' => 40,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lexical Search
    |--------------------------------------------------------------------------
    |
    | How the keyword half of hybrid search runs. `lexical_driver` is
    | "pg_textsearch" (true BM25 ranking; the extension must be preloaded
    | into Postgres), "postgres" (the built-in full-text search — works on
    | any Postgres, including managed databases), or "auto" to use
    | pg_textsearch when it is installed. `text_config` is the Postgres text
    | search configuration both drivers tokenize with; "simple" works for
    | every language (no stemming). After changing either, run `php artisan
    | chatvia:search:lexical-indexes` to rebuild the indexes.
    |
    */

    'search' => [
        'lexical_driver' => env('SEARCH_LEXICAL_DRIVER', 'auto'),
        'text_config' => env('SEARCH_TEXT_CONFIG', 'simple'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | `enabled` off drops analytics events before they reach the queue.
    | `connection` is the database connection of the analytics_* tables: the
    | "analytics" connection in config/database.php, a database of its own
    | next to the app's (DB_ANALYTICS_DATABASE). `queue` keeps analytics work
    | off the `messages` queue so it never slows a reply. `record_queue` is
    | for the quick database writes (analytics counters, audit and guardrail
    | rows), so they never wait behind the slow AI analysis on `queue`.
    |
    | `analysis`: chatvia:analytics:analyze-conversations reads each conversation once,
    | `scan_age_hours` after it starts, and retries a run that saved nothing
    | up to `max_attempts` runs. A new topic shows once it comes up in
    | `min_occurrences` conversations, with at most `max_examples` examples.
    |
    */

    'analytics' => [
        'enabled' => env('ANALYTICS_ENABLED', true),
        'connection' => env('ANALYTICS_DB_CONNECTION', 'analytics'),
        'queue' => env('ANALYTICS_QUEUE', 'analytics'),
        'record_queue' => env('ANALYTICS_RECORD_QUEUE', 'records'),

        'analysis' => [
            'scan_age_hours' => env('ANALYSIS_SCAN_AGE_HOURS', 6),
            'max_attempts' => 5,
            'min_occurrences' => env('TOPIC_SUGGESTION_MIN', 3),
            'max_examples' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool-Call Audit
    |--------------------------------------------------------------------------
    |
    | Every agent tool call is saved in the agent_activity table, its
    | arguments and result cut to `max_arg_bytes` and `max_result_bytes`
    | (hashed before the cut) and the values of `redact_keys` replaced.
    | `audit_enabled` off saves nothing. Rows older than `retention_days` are
    | pruned daily. Each agent may call one tool `tool_rate_limit` times per
    | `tool_rate_window_seconds`. The retention and the rate limit are set in
    | the admin panel (Settings → Security).
    |
    */

    'governance' => [
        'audit_enabled' => env('GOVERNANCE_AUDIT_ENABLED', true),
        'retention_days' => 90,
        'max_arg_bytes' => 4096,
        'max_result_bytes' => 8192,
        'redact_keys' => ['password', 'token', 'secret', 'authorization', 'api_key', 'apikey', 'access_token'],
        'tool_rate_limit' => 60,
        'tool_rate_window_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Audit Log
    |--------------------------------------------------------------------------
    |
    | Sign-ins, membership, configuration and data operations are saved in
    | the security_audit_log table, the metadata cut to `max_metadata_bytes`
    | (hashed before the cut) and the values of `redact_keys` replaced.
    | Rows are kept `retention_days`; a workspace may keep them for a shorter
    | time, but never under `retention_floor_days`. `enabled`, the retention
    | and the floor are set in the admin panel (Settings → Security).
    |
    */

    'security_audit' => [
        'enabled' => true,
        'retention_days' => 365,
        'retention_floor_days' => 30,
        'max_metadata_bytes' => 8192,
        'redact_keys' => ['password', 'token', 'secret', 'authorization', 'api_key', 'apikey', 'access_token', 'webhook_secret'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Conversations are kept `conversations_days`; a workspace may keep them
    | for a shorter time, never under `conversations_floor_days`. Both are
    | set in the admin panel (Settings → Workspaces).
    |
    | A workspace may also set how long leads and analytics are kept and when
    | chat sessions are anonymized (Settings → Security); nothing is deleted
    | while one is blank. These are the shortest windows it may pick, and
    | `max_days` the longest.
    |
    */

    'retention' => [
        'conversations_days' => ConversationRetention::DEFAULT_DAYS,
        'conversations_floor_days' => 1,
        'leads_floor_days' => 1,
        'analytics_floor_days' => 30,
        'anonymize_floor_days' => 1,
        'max_days' => 3650,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The dashboard's notification center keeps a notification this many
    | days after its last activity, read or not; `chatvia:prune:notifications`
    | deletes older ones every night. Set in the admin panel (Settings →
    | Workspaces).
    |
    */

    'notifications' => [
        'retention_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | SIEM Webhook
    |--------------------------------------------------------------------------
    |
    | The security audit log is also sent, signed, to a workspace's SIEM
    | endpoint. After `max_failures` failures in a row the webhook turns
    | itself off.
    |
    */

    'siem' => [
        'max_failures' => (int) env('SIEM_WEBHOOK_MAX_FAILURES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM Webhook
    |--------------------------------------------------------------------------
    |
    | Captured leads are also sent, signed, to an agent's CRM endpoint. After
    | `max_failures` failures in a row the webhook turns itself off.
    |
    */

    'crm' => [
        'max_failures' => (int) env('CRM_WEBHOOK_MAX_FAILURES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Evaluation
    |--------------------------------------------------------------------------
    |
    | Eval suites run each test case through the agent's real pipeline on
    | `queue`, off the chat queue. A run has at most `max_cases_per_run`
    | cases, and each stored answer is cut to `max_output_bytes`.
    |
    */

    'evals' => [
        'queue' => env('EVALS_QUEUE', 'analytics'),
        'max_cases_per_run' => (int) env('EVALS_MAX_CASES', 100),
        'max_output_bytes' => 8192,
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardrails
    |--------------------------------------------------------------------------
    |
    | The safety checks of the live chat (personal data, prompt injection,
    | blocked topics, toxicity). Each agent has its own settings; `enabled`
    | off runs no check at all. Triggers are saved in the guardrail_events
    | table on `queue`, with a snippet cut to `max_snippet_bytes`, and kept
    | `retention_days`. `enabled` and the retention are set in the admin
    | panel (Settings → Security).
    |
    */

    'guardrails' => [
        'enabled' => true,
        'queue' => env('GUARDRAILS_QUEUE', 'analytics'),
        'retention_days' => 90,
        'max_snippet_bytes' => 2048,
        // The longest message a visitor may send in the hosted chat or the
        // widget; a longer one is refused before any model runs.
        'max_message_chars' => 4000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice Conversations
    |--------------------------------------------------------------------------
    |
    | Live voice calls with an agent (`ai.voice`). The browser talks to the
    | voice service directly, and a `voice` queue worker joins each call to
    | search the knowledge, run tools and save the transcript.
    |
    | `max_call_minutes` is set in the admin panel (Settings → AI models),
    | at most 25 so the job ends before the queue retries it. A call offers
    | at most `max_tools` agent tools, fixed when it starts. The browser
    | waits `join_timeout` seconds for the worker before it gives up.
    |
    */

    'voice' => [
        'max_call_minutes' => 10,
        'max_tools' => 20,
        'join_timeout' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stream Replays
    |--------------------------------------------------------------------------
    |
    | A chat stream is remembered by the client's request id for
    | `ttl_seconds` in the `store` cache store (null is the default store),
    | so a retry with the same id replays the answer instead of asking the
    | model again.
    |
    */

    'sse' => [
        'dedupe' => [
            'store' => env('SSE_DEDUPE_STORE'),
            'ttl_seconds' => 600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    |
    | `script_url_embed` is the widget's embed.js: a path on this server
    | (the copy `npm run shakomako:build` puts in public/shakomako) or a full
    | address, such as a CDN or the widget dev server. `reverb` is the public
    | address of the WebSocket server, sent to the widget for live-agent
    | handoff; empty means the server of the widget API.
    |
    | Every widget may load on this install's own host and on
    | `extra_allowed_origins` (comma-separated hosts), on top of each
    | agent's own sites. `stream_per_agent_per_minute` and `_per_hour` cap
    | the replies one agent's widgets may stream, whatever the visitor's
    | session or IP. The script, the extra hosts and the caps are set in the
    | admin panel (Settings → Platform).
    |
    */

    'widget' => [
        'script_url_embed' => '/shakomako/embed.js',

        // Whether the widget header says the visitor is talking to an AI
        // assistant (EU AI Act Art. 50). Admin panel → Settings → Widget.
        'ai_disclosure' => true,

        // What a visitor's "remove this chat" does (ChatDeletionMode): `end`,
        // `hide` or `delete`. Admin panel → Settings → Widget.
        'chat_deletion_mode' => 'end',

        'reverb' => [
            'host' => env('VITE_REVERB_HOST'),
            'port' => env('VITE_REVERB_PORT'),
            'scheme' => env('VITE_REVERB_SCHEME'),
        ],

        'always_allowed_origins' => array_values(array_filter([
            Uri::of((string) env('APP_URL', ''))->host(),
        ])),
        'extra_allowed_origins' => '',

        'stream_per_agent_per_minute' => 120,
        'stream_per_agent_per_hour' => 1500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | `csp` is "off" (no policy), "report-only" (browsers only report what
    | the policy would block) or "enforce" (browsers apply it). In every mode
    | the pages other than the embeddable hosted chat may only be framed by
    | the app itself, and HSTS goes out in production over HTTPS.
    | `csp_report_uri` receives the reports. `allow_private_addresses` lets
    | server-side requests to webhooks, MCP servers and crawled sites reach
    | private and loopback addresses (for local development; off by
    | default). `turnstile` lists the public forms that ask for
    | the Cloudflare Turnstile bot check once its keys are saved. All are set
    | in the admin panel (Settings → Security).
    |
    */

    'security' => [
        'csp' => 'report-only',
        'csp_report_uri' => null,
        'allow_private_addresses' => false,

        'turnstile' => [
            'sign_up' => true,
            'sign_in' => false,
            'password_reset' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Unfinished Features
    |--------------------------------------------------------------------------
    |
    | Switches for features that are not finished. While one is off, the
    | feature's routes answer 404 and the dashboard hides it.
    | `youtube_sources` adds YouTube videos as knowledge sources; added
    | videos are saved but not ingested yet.
    |
    */

    'features' => [
        'youtube_sources' => (bool) env('FEATURE_YOUTUBE_SOURCES', false),
    ],

];
