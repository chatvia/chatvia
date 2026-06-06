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
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
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

    'chunker' => [
        'hash_key' => env('CHUNKER_HASH_KEY'),
    ],

    // Shared secret signing internal calls to our own built-in MCP servers
    // (/mcp/*) and ingestion callbacks. Falls back to APP_KEY when unset so it
    // is never empty; set explicitly to rotate independently of APP_KEY.
    'builtin_mcp' => [
        'secret' => env('BUILTIN_MCP_SECRET'),
    ],

    'ingestion' => [
        'secret' => env('INGESTION_WEBHOOK_SECRET'),
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
    ],
];
