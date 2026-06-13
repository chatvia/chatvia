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

    'perplexity' => [
        'slug' => 'perplexity',
        'name' => 'Perplexity',
        'description' => 'Search the live web with Perplexity Sonar — news, prices, recent events — with cited sources.',
        'category' => 'Utilities',
        'icon' => 'perplexity',
        'brand_color' => '22B8CD',
        'logo_url' => '/images/logos/connectors/perplexity.svg',
        'provider' => 'chatvia',
        'priority' => 5,
        // We host the MCP server but never pay for the upstream search — the
        // customer supplies their own Perplexity API key, sent to /mcp/perplexity
        // as a Bearer token and read back by PerplexityTool.
        'auth_strategy' => 'token',
        'auth_type' => 'bearer',
        'token_label' => 'Perplexity API key',
        'token_help_url' => 'https://www.perplexity.ai/account/api/keys',
        'token_placeholder' => 'Your Perplexity API key',
        'server_category' => 'integration',
        'mcp_url' => env('APP_URL').'/mcp/perplexity',
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
        // Customer supplies their own OpenWeatherMap API key — sent to
        // /mcp/weather as a Bearer token and read back by CurrentWeatherTool.
        'auth_strategy' => 'token',
        'auth_type' => 'bearer',
        'token_label' => 'OpenWeatherMap API key',
        'token_help_url' => 'https://home.openweathermap.org/api_keys',
        'token_placeholder' => 'Your OpenWeatherMap API key',
        'server_category' => 'integration',
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
        // Customer supplies their own ExchangeRate-API key — sent to
        // /mcp/currency as a Bearer token and read back by ExchangeRateTool.
        'auth_strategy' => 'token',
        'auth_type' => 'bearer',
        'token_label' => 'ExchangeRate-API key',
        'token_help_url' => 'https://app.exchangerate-api.com/dashboard',
        'token_placeholder' => 'Your ExchangeRate-API key',
        'server_category' => 'integration',
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
        // First-party builtin: always-on for every agent via the global
        // (user_id IS NULL) mcp_servers row synced by app:sync-builtin-mcp.
        'server_category' => 'builtin',
        // Hidden from the attachable catalog — it's always on, so there's
        // nothing to attach and no per-agent 'charts' row to collide with.
        'visible' => false,
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
        // First-party builtin, but NOT synced as a global always-on row, so it
        // stays per-agent opt-in (lead capture is an agent-specific choice).
        'server_category' => 'builtin',
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
        // Atlassian's `authv2` MCP endpoint is a spec-compliant OAuth 2.1 server
        // with Dynamic Client Registration — its metadata advertises a
        // `registration_endpoint` (auth.atlassian.com/{id}/dcr/register), so we
        // discover + register automatically like Linear/Fireflies. No
        // pre-registered Atlassian app or env vars needed. (Leaving the explicit
        // oauth_* endpoints here would trip McpOAuthFlow's pre-flight guard and
        // force the pre-registered path, so they must stay absent.)
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.atlassian.com/v1/mcp/authv2',
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

    'zendesk' => [
        'slug' => 'zendesk',
        'name' => 'Zendesk',
        'description' => 'Read tickets, users, and Help Center articles from your Zendesk Support account.',
        'category' => 'Communication',
        'icon' => 'zendesk',
        'brand_color' => '03363D',
        'logo_url' => '/images/logos/connectors/zendesk.svg',
        'priority' => 35,
        // Zendesk does not host a first-party MCP server, so there is no fixed
        // endpoint to bake in. Each workspace runs its own Zendesk MCP server
        // (self-hosted / community / partner) and supplies that URL together
        // with a Zendesk API token at connect time. `requires_url` flips the
        // token connect modal into URL + token mode; `mcp_url` stays empty.
        'auth_strategy' => 'token',
        'requires_url' => true,
        'mcp_url' => '',
        'auth_type' => 'bearer',
        'token_label' => 'Zendesk API token',
        'token_help_url' => 'https://support.zendesk.com/hc/en-us/articles/4408889192858-Managing-access-to-the-Zendesk-API',
        'token_placeholder' => 'Your Zendesk API token',
    ],

    'hubspot' => [
        'slug' => 'hubspot',
        'name' => 'HubSpot',
        'description' => 'Read and update contacts, companies, deals, and tickets in your HubSpot CRM.',
        'category' => 'Sales',
        'icon' => 'hubspot',
        'brand_color' => 'FF7A59',
        'logo_url' => '/images/logos/connectors/hubspot.svg',
        'priority' => 45,
        // HubSpot's hosted MCP authenticates with OAuth 2.1 + PKCE but has no
        // open Dynamic Client Registration: create an "MCP Auth App" in HubSpot
        // (Settings → Integrations → MCP Auth Apps) to get credentials, then set
        // the env vars below. Callback URL: APP_URL + '/connectors/oauth/callback'.
        // The requested scopes must match those granted to the MCP Auth App.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.hubspot.com',
        'oauth_client_id' => env('HUBSPOT_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('HUBSPOT_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://app.hubspot.com/oauth/authorize',
        'oauth_token_endpoint' => 'https://api.hubapi.com/oauth/v1/token',
        'oauth_scopes' => 'crm.objects.contacts.read crm.objects.companies.read crm.objects.deals.read tickets',
    ],

    'salesforce' => [
        'slug' => 'salesforce',
        'name' => 'Salesforce',
        'description' => 'Read and update accounts, contacts, opportunities, and cases from your Salesforce org.',
        'category' => 'Sales',
        'icon' => 'salesforce',
        'brand_color' => '00A1E0',
        'logo_url' => '/images/logos/connectors/salesforce.svg',
        'priority' => 48,
        // Salesforce Hosted MCP Servers (GA Apr 2026) authenticate with OAuth 2.0
        // + PKCE, but the client is PER-ORG: each workspace creates its own
        // External Client App (Consumer Key) and there is no Dynamic Client
        // Registration — so no single global app to bake in like GitHub/HubSpot.
        // `requires_client_id` routes connect through a modal that collects the
        // workspace's Consumer Key + org environment + MCP server; the OAuth
        // endpoints and the MCP URL (api.salesforce.com/platform/mcp/v1/...) are
        // derived from those choices in SalesforceConnectController. mcp_api +
        // refresh_token (offline) scopes are required for a durable refresh token.
        'auth_strategy' => 'oauth',
        'requires_client_id' => true,
        'mcp_url' => '',
        'oauth_scopes' => 'mcp_api refresh_token',
        'token_help_url' => 'https://developer.salesforce.com/docs/platform/hosted-mcp-servers/guide/create-external-client-app.html',
    ],

    'box' => [
        'slug' => 'box',
        'name' => 'Box',
        'description' => 'Search and read files, folders, and metadata from your Box account.',
        'category' => 'Productivity',
        'icon' => 'box',
        'brand_color' => '0061D5',
        'logo_url' => '/images/logos/connectors/box.svg',
        'priority' => 55,
        // Box's hosted MCP server (https://mcp.box.com) does not support Dynamic
        // Client Registration. Create a Box MCP server entry in the Box Admin
        // Console (Integrations) to get a Client ID / Secret, then set the env
        // vars below. Callback URL: APP_URL + '/connectors/oauth/callback'.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.box.com',
        'oauth_client_id' => env('BOX_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('BOX_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://account.box.com/api/oauth2/authorize',
        'oauth_token_endpoint' => 'https://api.box.com/oauth2/token',
        'oauth_scopes' => 'root_readwrite',
    ],

    'asana' => [
        'slug' => 'asana',
        'name' => 'Asana',
        'description' => 'Read and update tasks, projects, and portfolios across your Asana workspace.',
        'category' => 'Productivity',
        'icon' => 'asana',
        'brand_color' => 'F06A6A',
        'logo_url' => '/images/logos/connectors/asana.svg',
        'priority' => 65,
        // Asana's V2 MCP server (https://mcp.asana.com/v2/mcp) does not support
        // Dynamic Client Registration. Register an OAuth app at
        // https://app.asana.com/0/my-apps, add APP_URL + '/connectors/oauth/callback'
        // to its redirect URLs (Asana allowlists them), then set the env vars below.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.asana.com/v2/mcp',
        'oauth_client_id' => env('ASANA_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('ASANA_OAUTH_CLIENT_SECRET'),
        'oauth_authorization_endpoint' => 'https://app.asana.com/-/oauth_authorize',
        'oauth_token_endpoint' => 'https://app.asana.com/-/oauth_token',
        'oauth_scopes' => 'default',
    ],

    'sentry' => [
        'slug' => 'sentry',
        'name' => 'Sentry',
        'description' => 'Read issues, errors, and releases from your Sentry projects.',
        'category' => 'Developer Tools',
        'icon' => 'sentry',
        'brand_color' => '362D59',
        'logo_url' => '/images/logos/connectors/sentry.svg',
        'priority' => 75,
        // Sentry's hosted MCP (https://mcp.sentry.dev/mcp) supports OAuth Dynamic
        // Client Registration — no app registration needed.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.sentry.dev/mcp',
    ],

    'fireflies' => [
        'slug' => 'fireflies',
        'name' => 'Fireflies',
        'description' => 'Search meeting transcripts, summaries, and action items from Fireflies.ai.',
        'category' => 'Productivity',
        'icon' => 'fireflies',
        'brand_color' => 'FF6B00',
        'logo_url' => '/images/logos/connectors/fireflies.svg',
        'priority' => 85,
        // Fireflies' hosted MCP is a spec-compliant OAuth 2.1 server with
        // Dynamic Client Registration (its metadata advertises a
        // `registration_endpoint`), so — like Linear — we just point at the
        // MCP URL and McpOAuthFlow discovers + registers automatically. No
        // pre-registered app or env vars needed (unlike GitHub, which lacks
        // DCR). Users sign in with Fireflies instead of pasting an API key.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://api.fireflies.ai/mcp',
    ],

    'amplitude' => [
        'slug' => 'amplitude',
        'name' => 'Amplitude',
        'description' => 'Query product analytics — events, charts, and cohorts — from your Amplitude project.',
        'category' => 'Analytics',
        'icon' => 'amplitude',
        'brand_color' => '1E61F0',
        'logo_url' => '/images/logos/connectors/amplitude.svg',
        'priority' => 95,
        // Amplitude's hosted MCP (https://mcp.amplitude.com/mcp) supports OAuth
        // Dynamic Client Registration — no app registration needed.
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.amplitude.com/mcp',
    ],

    'serpapi' => [
        'slug' => 'serpapi',
        'name' => 'SerpApi',
        'description' => 'Search Google, Bing, YouTube, and other engines for live results — news, prices, locations — powered by SerpApi.',
        'category' => 'Utilities',
        'icon' => 'serpapi',
        'brand_color' => '377FEA',
        'logo_url' => '/images/logos/connectors/serpapi.svg',
        'priority' => 15,
        // SerpApi hosts its own MCP server at a fixed endpoint; the workspace
        // supplies its SerpApi API key, sent as a Bearer token. SerpApi also
        // accepts a path-based key (/KEY/mcp), but the header form keeps the key
        // out of the stored `url` and lets every workspace share the canonical
        // catalog (shouldUseCentralCatalog matches on the fixed mcp_url).
        'auth_strategy' => 'token',
        'auth_type' => 'bearer',
        'token_label' => 'SerpApi API key',
        'token_help_url' => 'https://serpapi.com/manage-api-key',
        'token_placeholder' => 'Your SerpApi API key',
        'mcp_url' => 'https://mcp.serpapi.com/mcp',
    ],
];
