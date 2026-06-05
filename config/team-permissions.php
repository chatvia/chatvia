<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Team Permissions
    |--------------------------------------------------------------------------
    |
    | Two-level permission system:
    | 1. Role Permissions - What actions can the user perform?
    | 2. Agent Access - Which specific agents can they access? (per role)
    |
    | Each permission has:
    | - label: Short name shown in UI
    | - description: Tooltip description explaining what the permission does
    | - requires: Array of permission codes that must be enabled for this to work
    |
    | Owners automatically have full access to everything.
    |
    */

    'permissions' => [
        // Agent Access Control
        'agents.access-all' => [
            'label' => 'Access all agents',
            'description' => 'Grant access to all current and future agents in the team',
        ],

        // Agent permissions (create/delete/rename only by company owner)
        'agent.setup' => [
            'label' => 'Setup agent',
            'description' => 'Modify agent settings, personality, and behavior',
        ],

        // Sources - Training by type
        'agent.sources.train.files' => [
            'label' => 'Train Files',
            'description' => 'Upload and process documents (PDF, Word, etc.)',
        ],
        'agent.sources.train.text' => [
            'label' => 'Train Text',
            'description' => 'Upload and process plain text files',
        ],
        'agent.sources.train.qa' => [
            'label' => 'Train Q&A',
            'description' => 'Add question and answer pairs',
        ],
        'agent.sources.train.web' => [
            'label' => 'Train Web',
            'description' => 'Crawl and process website content',
        ],
        'agent.sources.train.audio' => [
            'label' => 'Train Audio',
            'description' => 'Upload and transcribe audio files',
        ],
        'agent.sources.train.video' => [
            'label' => 'Train Video',
            'description' => 'Upload and transcribe video files',
        ],
        'agent.sources.train.functions' => [
            'label' => 'Manage Functions',
            'description' => 'Define LLM tool calls that hit your own webhook endpoints',
        ],
        'agent.sources.train.connectors' => [
            'label' => 'Manage Connectors',
            'description' => 'Connect third-party services (Stripe, GitHub, Notion, …) as live data sources',
        ],
        'agent.sources.delete' => [
            'label' => 'Delete sources',
            'description' => 'Remove training sources from the agent',
        ],

        // Chat
        'agent.chat.view' => [
            'label' => 'View conversations',
            'description' => 'Read chat history and conversation logs',
        ],
        'agent.chat.pulse' => [
            'label' => 'Conversation Pulse',
            'description' => 'View real-time conversation health metrics and sentiment tracking',
            'requires' => ['agent.chat.view'],
        ],
        'agent.chat.takeover' => [
            'label' => 'Take over',
            'description' => 'Take control of a live conversation from the AI',
            'requires' => ['agent.chat.view'],
        ],
        'agent.chat.delete' => [
            'label' => 'Delete conversations',
            'description' => 'Permanently delete conversation history',
            'requires' => ['agent.chat.view'],
        ],
        'agent.chat.export' => [
            'label' => 'Export conversations',
            'description' => 'Export chat history to CSV or other formats',
            'requires' => ['agent.chat.view'],
        ],

        // Analytics
        'agent.analytics.chats' => [
            'label' => 'Chat analytics',
            'description' => 'View conversation volume and trends',
        ],
        'agent.analytics.sentiment' => [
            'label' => 'Sentiment',
            'description' => 'View sentiment analysis of conversations',
        ],
        'agent.analytics.tone' => [
            'label' => 'Tones',
            'description' => 'View tone analysis of conversations',
        ],
        'agent.analytics.topics' => [
            'label' => 'Topics',
            'description' => 'View and manage topics',
        ],
        'agent.analytics.sources' => [
            'label' => 'Sources',
            'description' => 'View which sources are being used in responses',
        ],

        // Topics
        'agent.topics.manage' => [
            'label' => 'Manage topics',
            'description' => 'Create, edit, and delete conversation topics',
        ],

        // Leads
        'agent.leads.view' => [
            'label' => 'View leads',
            'description' => 'See captured lead information',
        ],
        'agent.leads.settings' => [
            'label' => 'Lead settings',
            'description' => 'Configure lead capture forms and rules',
            'requires' => ['agent.leads.view'],
        ],
        'agent.leads.export' => [
            'label' => 'Export leads',
            'description' => 'Download leads as CSV or integrate with CRM',
            'requires' => ['agent.leads.view'],
        ],

        // Agent administration (per-agent owner-style actions)
        'agent.members.manage' => [
            'label' => 'Agent Members',
            'description' => 'Invite, view, and remove people granted access to this agent',
        ],
        'agent.identity.manage' => [
            'label' => 'Agent Identity Verification',
            'description' => 'View and use the per-agent signing secret for sign-in tokens',
        ],

        // Account (team-level settings — Settings sidebar items)
        'account.api_keys' => [
            'label' => 'API keys',
            'description' => 'Create and revoke personal API tokens',
        ],
        'account.connectors' => [
            'label' => 'Connectors',
            'description' => 'Manage account-level third-party connectors (MCP servers)',
        ],
        'account.functions' => [
            'label' => 'Functions',
            'description' => 'Define webhook functions agents can call',
        ],
        'account.prompts' => [
            'label' => 'Prompts',
            'description' => 'Manage the shared prompts library',
        ],
        'account.members.manage' => [
            'label' => 'Members',
            'description' => 'View, reset password, and remove chat members across the workspace',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    */

    'roles' => [
        'owner' => [
            'name' => 'Owner',
            'description' => 'Full access to all agents and settings',
            'permissions' => ['*'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Scopes
    |--------------------------------------------------------------------------
    |
    | Top-level categories that group all permissions by what they govern.
    | 'agent' permissions apply per-agent (to the agents the role grants access
    | to); 'account' permissions apply once across the whole account / team
    | and live under the Settings menu.
    |
    */

    'scopes' => [
        'agent' => [
            'label' => 'Agent permissions',
            'description' => 'What this role can do inside each agent it has access to. These actions apply per agent — picked from the Agent Access list above.',
        ],
        'account' => [
            'label' => 'Account permissions',
            'description' => 'Shared, account-wide tools and settings. These apply once across every agent and are managed from the Settings menu.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Groups (for UI display)
    |--------------------------------------------------------------------------
    |
    | Each group belongs to a scope. Within a scope, groups render side-by-side
    | as cards on the Create / Edit Role page.
    |
    */

    'groups' => [
        // --- Agent scope ---
        'setup' => [
            'scope' => 'agent',
            'label' => 'Setup',
            'description' => 'Edit the agent itself — personality, models, widget, embed.',
            'permissions' => [
                'agent.setup',
            ],
        ],
        'sources' => [
            'scope' => 'agent',
            'label' => 'Sources',
            'description' => 'Manage the training data the agent learns from.',
            'permissions' => [
                'agent.sources.train.files',
                'agent.sources.train.text',
                'agent.sources.train.qa',
                'agent.sources.train.web',
                'agent.sources.train.audio',
                'agent.sources.train.video',
                'agent.sources.delete',
            ],
        ],
        'chat' => [
            'scope' => 'agent',
            'label' => 'Chat',
            'description' => 'Read, take over, export, or delete live conversations.',
            'permissions' => [
                'agent.chat.view',
                'agent.chat.pulse',
                'agent.chat.takeover',
                'agent.chat.delete',
                'agent.chat.export',
            ],
        ],
        'analytics' => [
            'scope' => 'agent',
            'label' => 'Analytics',
            'description' => 'Inspect usage, sentiment, tone, topics, and source performance.',
            'permissions' => [
                'agent.analytics.chats',
                'agent.analytics.sentiment',
                'agent.analytics.tone',
                'agent.analytics.topics',
                'agent.analytics.sources',
            ],
        ],
        'topics' => [
            'scope' => 'agent',
            'label' => 'Topics',
            'description' => 'Curate the topics conversations are grouped under.',
            'permissions' => [
                'agent.topics.manage',
            ],
        ],
        'leads' => [
            'scope' => 'agent',
            'label' => 'Leads',
            'description' => 'See, configure, and export leads captured by the agent.',
            'permissions' => [
                'agent.leads.view',
                'agent.leads.settings',
                'agent.leads.export',
            ],
        ],
        'administration' => [
            'scope' => 'agent',
            'label' => 'Administration',
            'description' => 'Owner-style per-agent tasks — members and signing secret.',
            'permissions' => [
                'agent.members.manage',
                'agent.identity.manage',
            ],
        ],

        // --- Account scope ---
        'security' => [
            'scope' => 'account',
            'label' => 'Security',
            'description' => 'Personal credentials used to call the public API.',
            'permissions' => [
                'account.api_keys',
            ],
        ],
        'integrations' => [
            'scope' => 'account',
            'label' => 'Integrations',
            'description' => 'Connect third-party services and custom webhook functions, then attach them to any agent.',
            'permissions' => [
                'account.connectors',
                'account.functions',
            ],
        ],
        'library' => [
            'scope' => 'account',
            'label' => 'Library',
            'description' => 'Reusable prompts shared across the account.',
            'permissions' => [
                'account.prompts',
            ],
        ],
        'people' => [
            'scope' => 'account',
            'label' => 'People',
            'description' => 'Manage the chat members granted access to your agents.',
            'permissions' => [
                'account.members.manage',
            ],
        ],
    ],
];
