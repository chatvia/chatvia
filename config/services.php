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
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
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
    ],

    'azure_document_intelligence' => [
        'endpoint' => env('AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT'),
        'api_key' => env('AZURE_DOCUMENT_INTELLIGENCE_API_KEY'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'completion_endpoint' => env('GROQ_COMPLETION_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'api_key' => env('GOOGLE_API_KEY'),
    ],

    'microsoft' => [
        'secret_value' => env('MICROSOFT_SECRET_VALUE'),
        'secret_id' => env('MICROSOFT_SECRET_ID'),
    ],

    'bin' => [
        'node' => env('NODE_BIN_PATH'),
        'npm' => env('NPM_BIN_PATH'),
        'nvm_include_path' => env('NVM_INCLUDE_PATH'),
        'libreoffice' => env('LIBREOFFICE_BIN_PATH', '/opt/homebrew/bin/soffice'),
        'pdfinfo' => env('PDFINFO_BIN_PATH', '/opt/homebrew/bin/pdfinfo'),
    ],

    'llamaparse' => [
        'endpoint' => env('LLAMA_PARSE_ENDPOINT', 'https://api.cloud.llamaindex.ai/api/v1/parsing'),
        'api_key' => env('LLAMA_PARSE_API_KEY'),
    ],

    'libs' => [
        'tiktoken' => env('TIKTOKEN_PATH', '/usr/local/lib/libtiktoken.so'),
    ],
];
