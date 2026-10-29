<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as AWS, Resend and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    | Keys set to null here (OAuth apps, API keys, webhook secrets) are managed
    | in the admin panel under Settings. They are stored encrypted in the
    | database and put into this config when the app boots
    | (App\Admin\Settings\SettingsConfig). Do not read them from .env. The
    | addresses and models below can be changed there too; the values here are
    | the defaults while nothing is saved.
    |
    */

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

    // Email providers, set in the admin panel (Settings → Email).
    'mailgun' => [
        'domain' => null,
        'secret' => null,
        'endpoint' => 'api.mailgun.net',
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => null,
    ],

    'cloudflare' => [
        'account_id' => null,
        'token' => null,
    ],

    'slack' => [
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

    'cohere' => [
        'api_key' => null,
        'api_endpoint' => env('COHERE_API_ENDPOINT', 'https://api.cohere.com/v2'),
        'embedding_model' => env('COHERE_EMBEDDING_MODEL', 'embed-v4.0'),
        'rerank_model' => env('COHERE_RERANK_MODEL', 'rerank-v4.0-pro'),
    ],

    'jina' => [
        'api_key' => null,
        'api_endpoint' => env('JINA_API_ENDPOINT', 'https://api.jina.ai/v1'),
        'embedding_model' => env('JINA_EMBEDDING_MODEL', 'jina-embeddings-v3'),
    ],

    'voyage' => [
        'api_key' => null,
        'api_endpoint' => env('VOYAGE_API_ENDPOINT', 'https://api.voyageai.com/v1'),
        'embedding_model' => env('VOYAGE_EMBEDDING_MODEL', 'voyage-4'),
    ],

    // Embeddings, speech to text, voice conversations and reading aloud;
    // OpenAI chat models are added as a provider. The realtime_* keys are the
    // voice calls (Settings → AI models → Voice conversations): the
    // speech-to-speech model, the voice agents speak with unless they pick
    // their own, and the model that writes down what the visitor says. The
    // speech_* keys read replies aloud (Settings → AI models → Read aloud)
    // through the realtime model: OpenAI ends its speech endpoint's models
    // on 6 January 2027.
    'openai' => [
        'api_key' => null,
        'api_endpoint' => 'https://api.openai.com/v1',
        'embedding_model' => 'text-embedding-3-small',
        'transcription_model' => 'whisper-1',
        'realtime_model' => 'gpt-realtime-2.1',
        'realtime_voice' => 'marin',
        'realtime_transcription_model' => 'gpt-4o-mini-transcribe',
        'speech_model' => 'gpt-realtime-2.1-mini',
        'speech_voice' => 'marin',
        'image_model' => 'gpt-image-2.5-flare',
    ],

    // Reads agent replies aloud (Settings → AI models → Read aloud). Voices
    // belong to the account, so an empty voice uses its first recommended
    // one. The endpoint changes only for ElevenLabs' regional servers. Each
    // `image_model` / `video_model` here is only the one a generator uses
    // when it is given none: the admin lists the models that make pictures
    // and videos in Settings → AI models.
    'elevenlabs' => [
        'api_key' => null,
        'endpoint' => 'https://api.elevenlabs.io/v1',
        'speech_model' => 'eleven_flash_v2_5',
        'speech_voice' => null,
        'video_model' => 'veo-3.1-fast-generate-001',
    ],

    // Google Gemini embeddings (the Gemini API, not Vertex AI). Gemini chat
    // models are added as a provider. `google` is the sign-in app.
    'gemini' => [
        'api_key' => null,
        'api_endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        'embedding_model' => 'gemini-embedding-001',
        'image_model' => 'gemini-nano-banana-2.1',
        'video_model' => 'gemini-omni-1.1-flash',
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
        // Bare names are looked up on the PATH of the process that runs them
        // (usually the queue worker). A full path is set in the admin panel
        // (Settings → Platform → Programs) when a program is not on that PATH.
        'catdoc' => 'catdoc',
        'catppt' => 'catppt',
        'pandoc' => 'pandoc',
        'pdfinfo' => 'pdfinfo',
        'pdftotext' => 'pdftotext',
        'ffmpeg' => 'ffmpeg',
        'ffprobe' => 'ffprobe',
        // Backups (chatvia:backup:create; Settings → Backups → Programs):
        // pg_dump and pg_restore must be the server's major version or newer.
        'pg_dump' => 'pg_dump',
        'pg_restore' => 'pg_restore',
        'redis_cli' => 'redis-cli',
        'gpg' => 'gpg',
        'tar' => 'tar',
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

    // Azure AI Document Intelligence: reads PDFs, office files and images
    // (Settings → AI providers). Each Azure resource has its own endpoint.
    'azure_document_intelligence' => [
        'key' => null,
        'endpoint' => null,
        'model' => env('AZURE_DOCUMENT_INTELLIGENCE_MODEL', 'prebuilt-layout'),
        'api_version' => '2024-11-30',
    ],

    // Azure OpenAI: the chat models deployed in one Azure resource
    // (Settings → AI providers). Each resource has its own endpoint.
    'azure_openai' => [
        'api_key' => null,
        'endpoint' => null,
    ],

    // One key for the OpenRouter chat models, its embeddings and its rerankers.
    'openrouter' => [
        'api_key' => null,
        'embedding_model' => 'openai/text-embedding-3-small',
        'image_model' => 'google/gemini-nano-banana-2.1',
    ],

    'groq' => [
        'api_key' => null,
    ],

    // Video services (Settings → AI models → Video generation), each with
    // its own key (Settings → AI providers). Kling and BytePlus ModelArk
    // (Seedance) have regional servers, so their address can change.
    'kling' => [
        'api_key' => null,
        'endpoint' => 'https://api-singapore.klingai.com',
        'video_model' => 'kling-3.0-turbo',
    ],

    'byteplus' => [
        'api_key' => null,
        'endpoint' => 'https://ark.ap-southeast.bytepluses.com/api/v3',
        'video_model' => 'dreamina-seedance-2-0-fast-260128',
    ],

    'runway' => [
        'api_key' => null,
        'video_model' => 'gen4.5',
    ],

    // fal.ai: one key for many image and video models, open ones too.
    'fal' => [
        'api_key' => null,
        'image_model' => 'fal-ai/flux-2',
        'video_model' => 'fal-ai/wan/v2.2-a14b/text-to-video',
    ],

    'anthropic' => [
        'api_key' => null,
    ],

    'scalewayai' => [
        'api_key' => null,
        // Base URL for chat, embeddings and transcription. The generic
        // URL uses the API key's default project; set a project URL
        // (https://api.scaleway.ai/{project-id}/v1) to bill a specific project.
        'endpoint' => env('SCALEWAYAI_API_ENDPOINT', 'https://api.scaleway.ai/v1'),
        // Open-weight models used for embeddings and speech-to-text.
        'embedding_model' => env('SCALEWAYAI_EMBEDDING_MODEL', 'qwen3-embedding-8b'),
        'transcription_model' => env('SCALEWAYAI_TRANSCRIPTION_MODEL', 'whisper-large-v3'),
    ],

    'mistral' => [
        'api_key' => null,
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
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
        // Where the app reaches its own /mcp/ routes, when that is not
        // APP_URL. In Docker, APP_URL (for example http://localhost:8000)
        // means something else inside a container; the image's compose file
        // sets this to the web container. Empty uses APP_URL.
        'internal_url' => env('BUILTIN_MCP_INTERNAL_URL'),
    ],

    'turnstile' => [
        'key' => null,
        'secret' => null,
    ],
];
