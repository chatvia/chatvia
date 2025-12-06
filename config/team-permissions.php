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
        // Agent permissions
        'agents.create' => [
            'label' => 'Create agents',
            'description' => 'Allow creating new AI agents for the team',
        ],
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

        // Sources - Training by type
        'agent.sources.train.pdf' => [
            'label' => 'Train PDF',
            'description' => 'Upload and process PDF documents',
        ],
        'agent.sources.train.word' => [
            'label' => 'Train Word',
            'description' => 'Upload and process Word documents',
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
        'agent.sources.delete' => [
            'label' => 'Delete sources',
            'description' => 'Remove training sources from the agent',
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
                'agents.create',
                'agent.rename',
                'agent.setup',
                'agent.delete',
            ],
        ],
        'sources' => [
            'label' => 'Sources',
            'description' => 'Training management',
            'permissions' => [
                'agent.sources.train.pdf',
                'agent.sources.train.word',
                'agent.sources.train.text',
                'agent.sources.train.qa',
                'agent.sources.train.web',
                'agent.sources.train.audio',
                'agent.sources.train.video',
                'agent.sources.delete',
            ],
        ],
        'chat' => [
            'label' => 'Chat',
            'description' => 'Conversation management',
            'permissions' => [
                'agent.chat.view',
                'agent.chat.takeover',
                'agent.chat.delete',
                'agent.chat.export',
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
