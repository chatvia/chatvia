<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    | Keys set to null here (OAuth apps, API keys, webhook secrets) are managed
    | in the admin panel under Settings. They are stored encrypted in the
    | database and put into this config when the app boots
    | (App\Admin\Settings\SettingsStore). Do not read them from .env.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // Base URLs the built-in Weather / Currency MCP tools call. Each workspace
    // brings its own API key (sent to /mcp/* as a bearer token).
    'openweather' => [
        'base_url' => env('OPEN_WEATHER_BASE_URL', 'https://api.openweathermap.org/data/2.5'),
    ],

    'exchange_rate' => [
        'base_url' => env('EXCHANGE_RATE_API_BASE_URL', 'https://v6.exchangerate-api.com/v6'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => null,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'client_id' => null,
        'client_secret' => null,
        'redirect' => null,
    ],

    /*
     * One Meta app per channel: Messenger, Instagram and WhatsApp
     * (Domain\Channel\Enums\MetaApp). Customers connect their Pages, accounts
     * and numbers through Facebook Login for Business on these apps instead of
     * creating their own. All values except `graph_version` are managed in the
     * admin panel (Settings → Channels); an install may enter the same app for
     * every channel.
     *
     *   config_id             Facebook Login for Business configuration. Without
     *                         it, Messenger and Instagram fall back to a classic
     *                         `scope` request; WhatsApp Embedded Signup needs it.
     *   redirect              The fixed OAuth callback (allow-listed in the app).
     *   webhook_verify_token  The token pasted into the app's webhook settings.
     *   register_pin          6-digit PIN that registers a connected WhatsApp
     *                         number for the Cloud API. Never 000000.
     */
    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', 'v25.0'),
        'messenger' => [
            'app_id' => null,
            'app_secret' => null,
            'config_id' => null,
            'redirect' => null,
            'webhook_verify_token' => null,
        ],
        'instagram' => [
            'app_id' => null,
            'app_secret' => null,
            'config_id' => null,
            'redirect' => null,
            'webhook_verify_token' => null,
        ],
        'whatsapp' => [
            'app_id' => null,
            'app_secret' => null,
            'config_id' => null,
            'webhook_verify_token' => null,
            'register_pin' => null,
        ],
    ],

    'openrouter' => [
        'api_key' => null,
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'completion_endpoint' => env('OPENROUTER_COMPLETION_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
    ],

    'cohere' => [
        'api_key' => null,
        'api_endpoint' => env('COHERE_API_ENDPOINT', 'https://api.cohere.com/v2'),
        'embedding_model' => env('COHERE_EMBEDDING_MODEL', 'embed-v4.0'),
        'embedding_dimensions' => env('COHERE_EMBEDDING_DIMENSIONS', 1024),
        'rerank_model' => env('COHERE_RERANK_MODEL', 'rerank-v4.0-pro'),
    ],

    'jina' => [
        'api_key' => null,
        'api_endpoint' => env('JINA_API_ENDPOINT', 'https://api.jina.ai/v1'),
        'embedding_model' => env('JINA_EMBEDDING_MODEL', 'jina-embeddings-v3'),
        'embedding_dimensions' => env('JINA_EMBEDDING_DIMENSIONS', 1024),
    ],

    'groq' => [
        'api_key' => null,
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'completion_endpoint' => env('GROQ_COMPLETION_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions'),
    ],

    'google' => [
        'client_id' => null,
        'client_secret' => null,
        'redirect' => null,
    ],

    'microsoft' => [
        'client_id' => null,
        'client_secret' => null,
        'redirect' => null,
    ],

    'github' => [
        'client_id' => null,
        'client_secret' => null,
        'redirect' => null,
    ],

    'bin' => [
        'node' => env('NODE_BIN_PATH'),
        'npm' => env('NPM_BIN_PATH'),
        'nvm_include_path' => env('NVM_INCLUDE_PATH'),
        'chrome' => env('CHROME_EXECUTABLE_PATH'),
        // Bare names are looked up on the PATH of the process that runs them
        // (usually the queue worker). Set an absolute path when the binary
        // lives somewhere that PATH doesn't cover.
        'libreoffice' => env('LIBREOFFICE_BIN_PATH', 'soffice'),
        'pandoc' => env('PANDOC_BIN_PATH', 'pandoc'),
        'pdfinfo' => env('PDFINFO_BIN_PATH', 'pdfinfo'),
        'pdftotext' => env('PDFTOTEXT_BIN_PATH', 'pdftotext'),
        'ffmpeg' => env('FFMPEG_BIN_PATH', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    ],

    'firecrawl' => [
        'key' => null,
        'endpoint' => env('FIRECRAWL_API_ENDPOINT', 'https://api.firecrawl.dev/v2'),

        // Stage-2 scrape renders JavaScript in a headless browser (so SPA /
        // AJAX / client-rendered content is captured). `wait_for` is an extra
        // settle delay in MS for content that loads after the initial render;
        // `timeout` (MS) caps a slow render. Both env-tunable; keep them well
        // under the HTTP client timeout in Firecrawl::client(). `timeout` also
        // caps the stage-1 `/map` discovery call.
        'wait_for' => (int) env('FIRECRAWL_WAIT_FOR', 2000),
        'timeout' => (int) env('FIRECRAWL_SCRAPE_TIMEOUT', 60000),

        // Global ceiling on FireCrawl requests per minute, enforced across every
        // queue worker by the ThrottleFirecrawl job middleware. Website
        // ingestion fans a scrape job out per URL across up to 100 Horizon
        // processes; without this they burst past the plan's rate limit (429
        // "Rate limit exceeded"). Set to ~80% of your plan's per-minute scrape
        // limit. 0 disables throttling.
        'rate_per_minute' => (int) env('FIRECRAWL_SCRAPE_RATE_PER_MINUTE', 60),
    ],

    'ip2location' => [
        'key' => null,
        'endpoint' => env('IP2LOCATION_ENDPOINT', 'https://api.ip2location.io'),
    ],

    'scalewayai' => [
        'api_key' => null,
        // Base URL for chat, embeddings, rerank and transcription. The generic
        // URL uses the API key's default project; set a project URL
        // (https://api.scaleway.ai/{project-id}/v1) to bill a specific project.
        'endpoint' => env('SCALEWAYAI_API_ENDPOINT', 'https://api.scaleway.ai/v1'),
        // Open-weight models used for embeddings, reranking and speech-to-text.
        'embedding_model' => env('SCALEWAYAI_EMBEDDING_MODEL', 'qwen3-embedding-8b'),
        'rerank_model' => env('SCALEWAYAI_RERANK_MODEL', 'qwen3-embedding-8b'),
        'transcription_model' => env('SCALEWAYAI_TRANSCRIPTION_MODEL', 'whisper-large-v3'),
    ],

    'mistral' => [
        'api_key' => null,
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
        // The PDF chat model is chatvia.ai.models.document_chat.
        'ocr_model' => env('MISTRAL_OCR_MODEL', 'mistral-ocr-latest'),
        'embedding_model' => env('MISTRAL_EMBEDDING_MODEL', 'mistral-embed'),
    ],

    'perplexity' => [
        // The Perplexity connector is BYO-key: each customer supplies their own
        // API key at connect time, so no api_key lives here. These just set the
        // upstream endpoint + the model each tool calls. `agent_model` drives
        // the /v1/agent tools (web_search, fetch_url, finance_search,
        // people_search, run_code); ask/research stay on chat-completions.
        'base_url' => env('PERPLEXITY_BASE_URL', 'https://api.perplexity.ai'),
        'agent_model' => env('PERPLEXITY_AGENT_MODEL', 'perplexity/sonar'),
        'ask_model' => env('PERPLEXITY_ASK_MODEL', 'sonar-pro'),
        'research_model' => env('PERPLEXITY_RESEARCH_MODEL', 'sonar-reasoning-pro'),
    ],

    // Shared secret signing internal calls to our own built-in MCP servers
    // (/mcp/*). Must be set explicitly in every environment — InternalMcpSignature
    // fails closed (throws "Missing secret") when blank, so the built-in tool loop
    // refuses to sign rather than silently reuse another key.
    'builtin_mcp' => [
        'secret' => env('BUILTIN_MCP_SECRET'),
    ],

    'turnstile' => [
        'key' => null,
        'secret' => null,
    ],

    // Page-view analytics (production only). Nothing is loaded while the
    // domain is empty. The script URL can point at a self-hosted Plausible.
    'plausible' => [
        'domain' => env('PLAUSIBLE_DOMAIN'),
        'script_url' => env('PLAUSIBLE_SCRIPT_URL', 'https://plausible.io/js/script.js'),
    ],

    'chunkfit' => [
        'api_key' => null,
        'base_url' => env('CHUNK_FIT_BASE_URL', 'https://chunk.fit/api/v1'),
        'timeout' => (int) env('CHUNK_FIT_TIMEOUT', 120),

        // Unstructured `chunk_by_title` knobs (all in CHARACTERS). Sections are
        // combined up to `combine_text_under_n_chars` and capped at
        // `max_characters` — tuned (~250-350 token chunks) to avoid the
        // many-tiny-chunks problem of raw Markdown-header splitting.
        'by_title' => [
            'max_characters' => (int) env('CHUNK_FIT_MAX_CHARACTERS', 2000),
            'new_after_n_chars' => (int) env('CHUNK_FIT_NEW_AFTER_N_CHARS', 1500),
            'combine_text_under_n_chars' => (int) env('CHUNK_FIT_COMBINE_UNDER_N_CHARS', 1000),
        ],
    ],
];
