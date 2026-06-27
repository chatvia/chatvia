<?php

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
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'openweather' => [
        'key' => env('OPEN_WEATHER_API_KEY'),
    ],

    'exchange_rate' => [
        'key' => env('EXCHANGE_RATE_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'redirect' => env('SLACK_REDIRECT_URI'),
    ],

    /*
     * Chatvia's own Facebook (Meta) app. One app serves every workspace —
     * customers connect their Pages via Facebook Login for Business instead of
     * creating their own app. Used by the Messenger channel today; Instagram
     * and WhatsApp reuse the same app + OAuth core.
     */
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        // Facebook Login for Business configuration id (Use cases → Customize →
        // Facebook Login for Business → Configurations). Optional — when unset
        // the authorize URL falls back to a classic `scope` request.
        'config_id' => env('META_LOGIN_CONFIG_ID'),
        // Separate Login-for-Business configuration for Instagram (its own
        // permission set). When unset, the Instagram flow reuses `config_id`.
        'instagram_config_id' => env('META_INSTAGRAM_LOGIN_CONFIG_ID'),
        // Separate Login-for-Business configuration for WhatsApp Embedded Signup
        // (WhatsApp Business Account asset). When unset, reuses `config_id`.
        'whatsapp_config_id' => env('META_WHATSAPP_LOGIN_CONFIG_ID'),
        'graph_version' => env('META_GRAPH_VERSION', 'v25.0'),
        // Fixed OAuth redirects — each must be whitelisted in the app's Login
        // settings. Instagram has its own callback path.
        'redirect' => env('META_REDIRECT_URI'),
        'instagram_redirect' => env('META_INSTAGRAM_REDIRECT_URI'),
        // The single app-level verify token pasted into the app's webhook config
        // (shared by Messenger + Instagram + WhatsApp).
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        // 6-digit PIN used to register a connected WhatsApp number for the Cloud
        // API (POST /{phone_number_id}/register). Set a deliberate value, never 000000.
        'whatsapp_register_pin' => env('META_WHATSAPP_REGISTER_PIN'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_endpoint' => env('OPENROUTER_API_ENDPOINT', 'https://openrouter.ai/api/v1'),
        'completion_endpoint' => env('OPENROUTER_COMPLETION_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'api_endpoint' => env('OPENAI_API_ENDPOINT', 'https://api.openai.com/v1'),
        'completion_endpoint' => env('OPENAI_COMPLETION_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
    ],

    'ingestion' => [
        'api_endpoint' => env('INGESTION_ENDPOINT_URL', 'http://127.0.0.1:8080'),
        'secret' => env('INGESTION_WEBHOOK_SECRET'),
    ],

    'cohere' => [
        'api_key' => env('COHERE_API_KEY'),
        'api_endpoint' => env('COHERE_API_ENDPOINT', 'https://api.cohere.com/v2'),
        'embedding_model' => env('COHERE_EMBEDDING_MODEL', 'embed-v4.0'),
        'embedding_dimensions' => env('COHERE_EMBEDDING_DIMENSIONS', 1024),
    ],

    'jina' => [
        'api_key' => env('JINA_API_KEY'),
        'api_endpoint' => env('JINA_API_ENDPOINT', 'https://api.jina.ai/v1'),
        'embedding_model' => env('JINA_EMBEDDING_MODEL', 'jina-embeddings-v3'),
        'embedding_dimensions' => env('JINA_EMBEDDING_DIMENSIONS', 1024),
    ],

    'azure_document_intelligence' => [
        'endpoint' => env('AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT'),
        'api_key' => env('AZURE_DOCUMENT_INTELLIGENCE_API_KEY'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'completion_endpoint' => env('GROQ_COMPLETION_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_AUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_AUTH_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_AUTH_REDIRECT_URI'),
        'api_key' => env('GOOGLE_API_KEY'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'bin' => [
        'node' => env('NODE_BIN_PATH'),
        'npm' => env('NPM_BIN_PATH'),
        'nvm_include_path' => env('NVM_INCLUDE_PATH'),
        'chrome' => env('CHROME_EXECUTABLE_PATH'),
        'libreoffice' => env('LIBREOFFICE_BIN_PATH', '/opt/homebrew/bin/soffice'),
        'pandoc' => env('PANDOC_BIN_PATH', '/opt/homebrew/bin/pandoc'),
        'pdfinfo' => env('PDFINFO_BIN_PATH', '/opt/homebrew/bin/pdfinfo'),
        'ffmpeg' => env('FFMPEG_BIN_PATH', '/opt/homebrew/bin/ffmpeg'),
        'ffprobe' => env('FFPROBE_PATH', '/opt/homebrew/bin/ffprobe'),
    ],

    'llamaparse' => [
        'endpoint' => env('LLAMA_PARSE_ENDPOINT', 'https://api.cloud.llamaindex.ai/api/v1/parsing'),
        'api_key' => env('LLAMA_PARSE_API_KEY'),
    ],

    'libs' => [
        'tiktoken' => env('TIKTOKEN_PATH', '/usr/local/lib/libtiktoken.so'),
    ],

    'firecrawl' => [
        'key' => env('FIRECRAWL_API_KEY'),
        'endpoint' => env('FIRECRAWL_API_ENDPOINT', 'https://api.firecrawl.dev/v2'),

        // Stage-2 scrape renders JavaScript in a headless browser (so SPA /
        // AJAX / client-rendered content is captured). `wait_for` is an extra
        // settle delay in MS for content that loads after the initial render;
        // `timeout` (MS) caps a slow render. Both env-tunable; keep them well
        // under the HTTP client timeout in Firecrawl::client(). These also
        // drive `scrapeOptions` on the stage-1 crawl below.
        'wait_for' => (int) env('FIRECRAWL_WAIT_FOR', 2000),
        'timeout' => (int) env('FIRECRAWL_SCRAPE_TIMEOUT', 60000),

        // Global ceiling on FireCrawl requests per minute, enforced across every
        // queue worker by the ThrottleFirecrawl job middleware. Website
        // ingestion fans a scrape job out per URL across up to 100 Horizon
        // processes; without this they burst past the plan's rate limit (429
        // "Rate limit exceeded"). Set to ~80% of your plan's per-minute scrape
        // limit. 0 disables throttling.
        'rate_per_minute' => (int) env('FIRECRAWL_SCRAPE_RATE_PER_MINUTE', 60),

        // Stage-1 discovery uses the async `/crawl` endpoint (a full recursive
        // crawl, not the shallow `/map`). WebsiteCrawlerJob starts the crawl;
        // PollWebsiteCrawlJob then re-checks status every `crawl_poll_seconds`,
        // up to `crawl_max_polls` times, before giving up. The markdown FireCrawl
        // fetches during the crawl is cached for `markdown_cache_hours` so
        // stage-2 training reuses it instead of re-scraping every page.
        'crawl_poll_seconds' => (int) env('FIRECRAWL_CRAWL_POLL_SECONDS', 10),
        'crawl_max_polls' => (int) env('FIRECRAWL_CRAWL_MAX_POLLS', 120),
        'markdown_cache_hours' => (int) env('FIRECRAWL_MARKDOWN_CACHE_HOURS', 48),
    ],

    'voyageai' => [
        'api_key' => env('VOYAGEAI_API_KEY'),
        'api_endpoint' => env('VOYAGEAI_API_ENDPOINT', 'https://api.voyageai.com/v1'),
    ],

    'comprehend' => [
        'key' => env('AWS_COMPREHEND_ACCESS_KEY_ID'),
        'secret' => env('AWS_COMPREHEND_SECRET_ACCESS_KEY'),
        'region' => env('AWS_COMPREHEND_REGION', 'eu-central-1'),
    ],

    'ip2location' => [
        'key' => env('IP2LOCATION_API_KEY'),
        'endpoint' => env('IP2LOCATION_ENDPOINT', 'https://api.ip2location.io'),
    ],

    'scalewayai' => [
        'api_key' => env('SCALEWAYAI_API_KEY'),
        'endpoint' => env('SCALEWAYAI_API_ENDPOINT', 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
        // Model used by MistralPdfSource for PDF Q&A turns. Needs to support
        // both `document_url` content chunks AND function calling so the
        // tool loop can fire inside PDF conversations — small-latest doesn't.
        'qa_model' => env('MISTRAL_QA_MODEL', 'mistral-medium-latest'),
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

    'chunker' => [
        'hash_key' => env('CHUNKER_HASH_KEY'),
    ],

    // Shared secret signing internal calls to our own built-in MCP servers
    // (/mcp/*). Must be set explicitly in every environment — InternalMcpSignature
    // fails closed (throws "Missing secret") when blank, so the built-in tool loop
    // refuses to sign rather than silently reuse another key.
    'builtin_mcp' => [
        'secret' => env('BUILTIN_MCP_SECRET'),
    ],

    'turnstile' => [
        'key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    'chunkfit' => [
        'api_key' => env('CHUNK_FIT_API_KEY'),
        'base_url' => env('CHUNK_FIT_BASE_URL', 'https://chunk.fit/api/v1'),
        'timeout' => (int) env('CHUNK_FIT_TIMEOUT', 120),
        'tokenizer' => env('CHUNK_FIT_TOKENIZER', 'cl100k_base'),

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
