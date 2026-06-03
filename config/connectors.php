<?php

/**
 * MCP Connectors catalog.
 *
 * Each entry is a *preset* — a curated, brandable wrapper around an MCP
 * server endpoint. Users either paste a token (`auth_strategy: 'token'`)
 * or complete an OAuth 2.1 + PKCE flow with Dynamic Client Registration
 * (`auth_strategy: 'oauth'`). Either way, a row in `agent_mcp_servers`
 * gets provisioned pointing at the configured `mcp_url`.
 */

return [

    'web-search' => [
        'slug' => 'web-search',
        'name' => 'Web Search',
        'description' => 'Search the live web for up-to-date information — news, prices, recent events — with cited sources.',
        'category' => 'Utilities',
        'icon' => 'web-search',
        'brand_color' => 'F97316',
        'logo_url' => '/images/logos/connectors/web-search.svg',
        'provider' => 'chatvia',
        'priority' => 5,
        'auth_strategy' => 'none',
        'mcp_url' => env('APP_URL').'/mcp/web-search',
    ],

    'weather' => [
        'slug' => 'weather',
        'name' => 'Weather',
        'description' => 'Live weather (temperature, conditions, humidity, wind) for any city, powered by OpenWeatherMap.',
        'category' => 'Utilities',
        'icon' => 'weather',
        'brand_color' => '3B82F6',
        'logo_url' => '/images/logos/connectors/weather.svg',
        'provider' => 'chatvia',
        'priority' => 10,
        'auth_strategy' => 'none',
        'mcp_url' => env('APP_URL').'/mcp/weather',
    ],

    'currency' => [
        'slug' => 'currency',
        'name' => 'Currency Converter',
        'description' => 'Convert amounts between any two currencies using current exchange rates (exchangerate-api).',
        'category' => 'Utilities',
        'icon' => 'currency',
        'brand_color' => '10B981',
        'logo_url' => '/images/logos/connectors/currency-convertor.svg',
        'provider' => 'chatvia',
        'priority' => 20,
        'auth_strategy' => 'none',
        'mcp_url' => env('APP_URL').'/mcp/currency',
    ],

    'charts' => [
        'slug' => 'charts',
        'name' => 'Interactive charts',
        'description' => 'Render interactive charts inline in the chat — bar, line, pie, scatter, radar, gauge, heatmap, and more.',
        'category' => 'Utilities',
        'icon' => 'charts',
        'brand_color' => '8B5CF6',
        'logo_url' => '/images/logos/connectors/interactive-charts.svg',
        'provider' => 'chatvia',
        'priority' => 30,
        'auth_strategy' => 'none',
        'mcp_url' => env('APP_URL').'/mcp/charts',
    ],

    'leads' => [
        'slug' => 'leads',
        'name' => 'Leads',
        'description' => 'Capture visitor contact details (name, email, phone, and more)',
        'category' => 'Sales',
        'icon' => 'leads',
        'brand_color' => 'F59E0B',
        'logo_url' => '/images/logos/connectors/leads.svg',
        'provider' => 'chatvia',
        'priority' => 40,
        'auth_strategy' => 'none',
        'mcp_url' => env('APP_URL').'/mcp/leads',
    ],

    'stripe' => [
        'slug' => 'stripe',
        'name' => 'Stripe',
        'description' => 'Search transactions, customers, products, and payments in your Stripe account.',
        'category' => 'Payments',
        'icon' => 'stripe',
        'brand_color' => '635BFF',
        'logo_url' => '/images/logos/connectors/stripe.svg',
        'priority' => 100,
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.stripe.com',
    ],

    'github' => [
        'slug' => 'github',
        'name' => 'GitHub',
        'description' => 'Read repos, issues, PRs, and code from any account you have access to.',
        'category' => 'Developer Tools',
        'icon' => 'github',
        'brand_color' => '181717',
        'logo_url' => '/images/logos/connectors/github.svg',
        'priority' => 70,
        // GitHub doesn't support Dynamic Client Registration — you must
        // register an OAuth App at https://github.com/settings/applications/new
        // (callback URL: APP_URL + '/connectors/oauth/callback') and put
        // the credentials into the env vars below.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://api.githubcopilot.com/mcp/',
        'oauth_client_id' => env('GITHUB_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GITHUB_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://github.com/login/oauth/authorize',
        'oauth_token_endpoint' => 'https://github.com/login/oauth/access_token',
        'oauth_scopes' => 'repo read:org read:user user:email gist',
    ],

    'notion' => [
        'slug' => 'notion',
        'name' => 'Notion',
        'description' => 'Read pages, databases, and comments from your Notion workspace.',
        'category' => 'Productivity',
        'icon' => 'notion',
        'brand_color' => '000000',
        'logo_url' => '/images/logos/connectors/notion.svg',
        'priority' => 50,
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.notion.com/mcp',
    ],

    'linear' => [
        'slug' => 'linear',
        'name' => 'Linear',
        'description' => 'Search issues, projects, and cycles across your Linear workspace.',
        'category' => 'Productivity',
        'icon' => 'linear',
        'brand_color' => '5E6AD2',
        'logo_url' => '/images/logos/connectors/linear.svg',
        'priority' => 80,
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.linear.app/mcp',
    ],

    'atlassian' => [
        'slug' => 'atlassian',
        'name' => 'Atlassian',
        'description' => 'Jira issues, Confluence pages, and workflows from your Atlassian sites.',
        'category' => 'Productivity',
        'icon' => 'atlassian',
        'brand_color' => '0052CC',
        'logo_url' => '/images/logos/connectors/atlassian.svg',
        'priority' => 60,
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.atlassian.com/v1/mcp/authv2',
        'oauth_client_id' => env('ATLASSIAN_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('ATLASSIAN_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://auth.atlassian.com/authorize',
        'oauth_token_endpoint' => 'https://auth.atlassian.com/oauth/token',
        'oauth_scopes' => 'read:jira-work read:jira-user read:confluence-content.all read:confluence-space.summary read:confluence-user offline_access',
    ],

    'gmail' => [
        'slug' => 'gmail',
        'name' => 'Gmail',
        'description' => 'Search messages, read threads, and draft replies from your Gmail inbox.',
        'category' => 'Communication',
        'icon' => 'gmail',
        'brand_color' => 'EA4335',
        'logo_url' => '/images/logos/connectors/gmail.svg',
        'priority' => 10,
        // Google does not support OAuth 2.1 Dynamic Client Registration. Register
        // one OAuth 2.0 client at https://console.cloud.google.com/apis/credentials
        // (Application type: Web; Authorized redirect URI: APP_URL + '/connectors/oauth/callback').
        // The same client_id/secret is reused for Gmail, Calendar, and Drive — enable
        // each API in the Cloud Console project.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://gmailmcp.googleapis.com/mcp/v1',
        'oauth_client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'oauth_token_endpoint' => 'https://oauth2.googleapis.com/token',
        'oauth_scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
    ],

    'google-calendar' => [
        'slug' => 'google-calendar',
        'name' => 'Google Calendar',
        'description' => 'Read and create events across your Google Calendars.',
        'category' => 'Productivity',
        'icon' => 'google-calendar',
        'brand_color' => '4285F4',
        'logo_url' => '/images/logos/connectors/google-calendar.svg',
        'priority' => 30,
        // Shares the GOOGLE_OAUTH_CLIENT_* env vars with Gmail / Drive — see Gmail entry.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://calendarmcp.googleapis.com/mcp/v1',
        'oauth_client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'oauth_token_endpoint' => 'https://oauth2.googleapis.com/token',
        'oauth_scopes' => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly',
    ],

    'google-drive' => [
        'slug' => 'google-drive',
        'name' => 'Google Drive',
        'description' => 'Read files and folders from your Google Drive.',
        'category' => 'Productivity',
        'icon' => 'google-drive',
        'brand_color' => '0F9D58',
        'logo_url' => '/images/logos/connectors/google-drive.svg',
        'priority' => 40,
        // Shares the GOOGLE_OAUTH_CLIENT_* env vars with Gmail / Calendar — see Gmail entry.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://drivemcp.googleapis.com/mcp/v1',
        'oauth_client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'oauth_token_endpoint' => 'https://oauth2.googleapis.com/token',
        'oauth_scopes' => 'https://www.googleapis.com/auth/drive.readonly',
    ],

    'slack' => [
        'slug' => 'slack',
        'name' => 'Slack',
        'description' => 'Search messages, post replies, and read channel history across your Slack workspace.',
        'category' => 'Communication',
        'icon' => 'slack',
        'brand_color' => '4A154B',
        'logo_url' => '/images/logos/connectors/slack.svg',
        'priority' => 20,
        // Slack does not support OAuth DCR. Register a Slack app at
        // https://api.slack.com/apps (Redirect URL: APP_URL + '/connectors/oauth/callback')
        // and put the Client ID / Client Secret in the env vars below.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.slack.com/mcp',
        'oauth_client_id' => env('SLACK_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('SLACK_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://slack.com/oauth/v2_user/authorize',
        'oauth_token_endpoint' => 'https://slack.com/api/oauth.v2.user.access',
        'oauth_scopes' => 'search:read.public channels:history channels:read groups:history groups:read im:history im:read mpim:history mpim:read chat:write users:read',
    ],

    'calendly' => [
        'slug' => 'calendly',
        'name' => 'Calendly',
        'description' => 'Let visitors book meetings on your Calendly, and let agents read your scheduled events.',
        'category' => 'Productivity',
        'icon' => 'calendly',
        'brand_color' => '006BFF',
        'logo_url' => '/images/logos/connectors/calendly.svg',
        'priority' => 25,
        // Calendly's MCP server handles auth via OAuth dynamic client registration —
        // no app registration required on the developer's side.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.calendly.com',
    ],

    'todoist' => [
        'slug' => 'todoist',
        'name' => 'Todoist',
        'description' => 'Manage tasks, projects, and labels across your Todoist account.',
        'category' => 'Productivity',
        'icon' => 'todoist',
        'brand_color' => 'E44332',
        'logo_url' => '/images/logos/connectors/todoist.svg',
        'priority' => 90,
        // Todoist's MCP server handles auth via OAuth dynamic client registration —
        // no app registration required on the developer's side.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://ai.todoist.net/mcp',
    ],

    'zapier' => [
        'slug' => 'zapier',
        'name' => 'Zapier',
        'description' => 'Trigger Zapier actions across 9,000+ connected apps.',
        'category' => 'Automation',
        'icon' => 'zapier',
        'brand_color' => 'FF4A00',
        'logo_url' => '/images/logos/connectors/zapier.svg',
        'priority' => 110,
        // Zapier's MCP server appears to support OAuth dynamic client registration.
        // If first-connect fails with "client registration unsupported", register an
        // OAuth app at https://mcp.zapier.com and add ZAPIER_OAUTH_CLIENT_* env vars.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.zapier.com/api/mcp/mcp',
    ],
];
