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

    'weather' => [
        'slug' => 'weather',
        'name' => 'Weather',
        'description' => 'Live weather (temperature, conditions, humidity, wind) for any city, powered by OpenWeatherMap.',
        'category' => 'Utilities',
        'icon' => 'weather',
        'brand_color' => '3B82F6',
        'logo_url' => '/images/icons/weather.svg',
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
        'logo_url' => '/images/icons/currency.svg',
        'auth_strategy' => 'none',
        'mcp_url' => env('APP_URL').'/mcp/currency',
    ],

    'charts' => [
        'slug' => 'charts',
        'name' => 'Charts',
        'description' => 'Render interactive Apache ECharts charts inline in the chat — bar, line, pie, scatter, radar, gauge, heatmap, and more.',
        'category' => 'Utilities',
        'icon' => 'charts',
        'brand_color' => '8B5CF6',
        'logo_url' => '/images/icons/charts.svg',
        'auth_strategy' => 'none',
        'mcp_url' => env('APP_URL').'/mcp/charts',
    ],

    'stripe' => [
        'slug' => 'stripe',
        'name' => 'Stripe',
        'description' => 'Search transactions, customers, products, and payments in your Stripe account.',
        'category' => 'Payments',
        'icon' => 'stripe',
        'brand_color' => '635BFF',
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
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.atlassian.com/v1/sse', // TODO: verify Atlassian's streamable-HTTP endpoint path
    ],
];
