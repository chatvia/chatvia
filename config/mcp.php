<?php

use Domain\Integration\Mcp\Services\Servers\AccountAnalyticsServer;
use Domain\Integration\Mcp\Services\Servers\ChartsServer;

return [

    /*
    |--------------------------------------------------------------------------
    | Built-in MCP registry
    |--------------------------------------------------------------------------
    |
    | Chatvia's first-party MCP servers that `app:sync-builtin-mcp` catalogs as
    | GLOBAL (user_id IS NULL) rows in `mcp_servers` and embeds into `mcp_tools`,
    | so the agent-less "Ask your data" chat can semantically route over them
    | (BuiltinToolRouter) instead of shipping every tool to the model each turn.
    |
    | The endpoint is derived as `{app.url}/mcp/{slug}`. `category` is the
    | McpServerCategory value the global row carries.
    |
    */

    'builtin' => [
        [
            'slug' => 'account-analytics',
            'class' => AccountAnalyticsServer::class,
            'category' => 'analytics',
            'name' => 'Account Analytics',
            'description' => "Answer the workspace owner's questions about their own account in numbers — messages, conversations, users, channels, leads, satisfaction, response time, topics and handoffs.",
        ],
        // Charts is ALSO a per-agent opt-in connector (config/connectors.php).
        // This global row is NOT ambient for agents — it exists only so the
        // owner analytics "Ask your data" chat can route to the chart tools
        // (BuiltinToolRouter) and so the shared connector_tools catalog +
        // embeddings are pre-seeded before the first per-workspace connect.
        [
            'slug' => 'charts',
            'class' => ChartsServer::class,
            'category' => 'builtin',
            'name' => 'Interactive charts',
            'description' => 'Render interactive charts (bar, line, pie, gauge, scatter, radar, heatmap, …) inline in the chat.',
        ],
    ],

];
