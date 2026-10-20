<?php

declare(strict_types=1);

use App\Mcp\Servers\AccountAnalyticsServer;
use App\Mcp\Servers\ChartsServer;
use App\Mcp\Servers\FormsServer;

return [

    /*
    |--------------------------------------------------------------------------
    | Built-in MCP registry
    |--------------------------------------------------------------------------
    |
    | Chatvia's first-party MCP servers that `chatvia:mcp:sync-builtin` catalogs as
    | GLOBAL (user_id IS NULL) rows in `mcp_servers` and embeds into `mcp_tools`,
    | so the agent-less "Ask your data" chat can semantically route over them
    | (BuiltinToolRouter) instead of shipping every tool to the model each turn.
    |
    | The endpoint is derived as `{app.url}/mcp/{slug}`. `category` is the
    | McpServerCategory value the global row carries. `class` is the server
    | that defines the tools; the analytics chat also builds its fallback
    | toolset from it (AnalyticsToolset), and only these routes accept a
    | workspace caller (VerifyInternalMcpRequest). `tool_label` is the badge
    | the analytics chat shows on a call to one of the server's tools.
    |
    */

    'builtin' => [
        [
            'slug' => 'account-analytics',
            'class' => AccountAnalyticsServer::class,
            'category' => 'analytics',
            'tool_label' => 'Analytics',
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
            'tool_label' => 'Chart',
            'name' => 'Interactive charts',
            'description' => 'Render interactive charts (bar, line, pie, gauge, scatter, radar, heatmap, …) inline in the chat.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-agent capability servers
    |--------------------------------------------------------------------------
    |
    | First-party servers an agent opts into (Capabilities page). The deploy
    | sync catalogs their tools like the builtins above but creates NO global
    | row: a global Builtin-category row would surface their tools inside the
    | owner analytics chat, which routes over global rows.
    |
    */

    'capabilities' => [
        [
            'slug' => 'forms',
            'class' => FormsServer::class,
        ],
    ],

];
