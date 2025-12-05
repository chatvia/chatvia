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
        // Team-wide permissions
        'agents.create' => [
            'label' => 'Create agents',
            'description' => 'Allow creating new AI agents for the team',
        ],

        // Agent-scoped permissions (apply to assigned agents only)
        'agent.rename' => [
            'label' => 'Rename agent',
            'description' => 'Change the agent name',
        ],
        'agent.setup' => [
            'label' => 'Setup agent',
            'description' => 'Modify agent settings, personality, and behavior',
        ],
        'agent.delete' => [
            'label' => 'Delete agent',
            'description' => 'Permanently remove the agent and all its data',
        ],

        // Sources - Training
        'agent.sources.train' => [
            'label' => 'Train sources',
            'description' => 'Upload and process new training files',
        ],
        'agent.sources.delete' => [
            'label' => 'Delete sources',
            'description' => 'Remove training sources from the agent',
        ],

        // Sources - View
        'agent.sources.view.pdf' => [
            'label' => 'PDF files',
            'description' => 'View PDF document sources',
        ],
        'agent.sources.view.word' => [
            'label' => 'Word files',
            'description' => 'View Word document sources',
        ],
        'agent.sources.view.website' => [
            'label' => 'Websites',
            'description' => 'View crawled website sources',
        ],
        'agent.sources.view.qa' => [
            'label' => 'Q&A pairs',
            'description' => 'View question and answer pairs',
        ],
        'agent.sources.view.text' => [
            'label' => 'Text files',
            'description' => 'View plain text sources',
        ],
        'agent.sources.view.audio' => [
            'label' => 'Audio files',
            'description' => 'View transcribed audio sources',
        ],
        'agent.sources.view.video' => [
            'label' => 'Video files',
            'description' => 'View transcribed video sources',
        ],
        'agent.sources.view.youtube' => [
            'label' => 'YouTube',
            'description' => 'View YouTube video transcriptions',
        ],

        // Chat
        'agent.chat.view' => [
            'label' => 'View conversations',
            'description' => 'Read chat history and conversation logs',
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

        // Analytics
        'agent.analytics.chats' => [
            'label' => 'Chat analytics',
            'description' => 'View conversation volume and trends',
        ],
        'agent.analytics.metrics' => [
            'label' => 'Metrics',
            'description' => 'View performance metrics and KPIs',
        ],
        'agent.analytics.sentiment' => [
            'label' => 'Sentiment',
            'description' => 'View sentiment analysis of conversations',
        ],
        'agent.analytics.tone' => [
            'label' => 'Tone',
            'description' => 'View tone analysis of conversations',
        ],
        'agent.analytics.topics' => [
            'label' => 'Topics',
            'description' => 'View topic distribution and trends',
        ],
        'agent.analytics.sources' => [
            'label' => 'Sources',
            'description' => 'View which sources are being used in responses',
        ],

        // Topics
        'agent.topics.add' => [
            'label' => 'Add topics',
            'description' => 'Create new conversation topics',
        ],
        'agent.topics.edit' => [
            'label' => 'Edit topics',
            'description' => 'Modify existing topics',
            'requires' => ['agent.topics.add'],
        ],
        'agent.topics.delete' => [
            'label' => 'Delete topics',
            'description' => 'Remove topics from the agent',
            'requires' => ['agent.topics.add'],
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
            'all_agents' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Groups (for UI display)
    |--------------------------------------------------------------------------
    */

    'groups' => [
        'agent' => [
            'label' => 'Agent',
            'description' => 'Agent management',
            'permissions' => [
                'agent.rename',
                'agent.setup',
                'agent.delete',
            ],
        ],
        'sources' => [
            'label' => 'Sources',
            'description' => 'Training management',
            'permissions' => [
                'agent.sources.train',
                'agent.sources.delete',
            ],
        ],
        'sources_view' => [
            'label' => 'View Sources',
            'description' => 'Access to source types',
            'permissions' => [
                'agent.sources.view.pdf',
                'agent.sources.view.word',
                'agent.sources.view.text',
                'agent.sources.view.website',
                'agent.sources.view.qa',
                'agent.sources.view.audio',
                'agent.sources.view.video',
                'agent.sources.view.youtube',
            ],
        ],
        'chat' => [
            'label' => 'Chat',
            'description' => 'Conversation management',
            'permissions' => [
                'agent.chat.view',
                'agent.chat.takeover',
                'agent.chat.delete',
            ],
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'View performance data',
            'permissions' => [
                'agent.analytics.chats',
                'agent.analytics.metrics',
                'agent.analytics.sentiment',
                'agent.analytics.tone',
                'agent.analytics.topics',
                'agent.analytics.sources',
            ],
        ],
        'topics' => [
            'label' => 'Topics',
            'description' => 'Manage conversation topics',
            'permissions' => [
                'agent.topics.add',
                'agent.topics.edit',
                'agent.topics.delete',
            ],
        ],
        'leads' => [
            'label' => 'Leads',
            'description' => 'Lead management',
            'permissions' => [
                'agent.leads.view',
                'agent.leads.settings',
                'agent.leads.export',
            ],
        ],
    ],
];
