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
    | Owners automatically have full access to everything.
    |
    */

    'permissions' => [
        // Team-wide permissions
        'agents.create' => 'Create new agents',

        // Agent-scoped permissions (apply to assigned agents only)
        'agent.view' => 'View agent dashboard',
        'agent.setup' => 'Configure agent settings',
        'agent.delete' => 'Delete agent',

        // Sources
        'agent.sources.train' => 'Train files',
        'agent.sources.view.pdf' => 'View PDF sources',
        'agent.sources.view.word' => 'View Word sources',
        'agent.sources.view.website' => 'View website sources',
        'agent.sources.view.qa' => 'View Q&A sources',
        'agent.sources.view.text' => 'View text sources',
        'agent.sources.view.audio' => 'View audio sources',
        'agent.sources.view.video' => 'View video sources',
        'agent.sources.view.youtube' => 'View YouTube sources',
        'agent.sources.delete' => 'Delete sources',

        // Chat
        'agent.chat.view' => 'View conversations',
        'agent.chat.takeover' => 'Take over conversations',
        'agent.chat.delete' => 'Delete conversations',

        // Analytics
        'agent.analytics.chats' => 'View chat analytics',
        'agent.analytics.metrics' => 'View metrics',
        'agent.analytics.sentiment' => 'View sentiment analysis',
        'agent.analytics.tone' => 'View tone analysis',
        'agent.analytics.topics' => 'View topics analytics',
        'agent.analytics.sources' => 'View sources analytics',

        // Topics
        'agent.topics.add' => 'Add topics',
        'agent.topics.edit' => 'Edit topics',
        'agent.topics.delete' => 'Delete topics',

        // Leads
        'agent.leads.view' => 'View leads',
        'agent.leads.settings' => 'Configure lead capture',
        'agent.leads.export' => 'Export leads',

        // Billing (account-level)
        'billing.view' => 'View billing & invoices',
        'billing.manage' => 'Manage subscription',
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
        // Agent-specific permissions (apply to assigned agents)
        'agent' => [
            'label' => 'Agent',
            'description' => 'Agent management',
            'category' => 'agent',
            'permissions' => [
                'agent.view',
                'agent.setup',
                'agent.delete',
            ],
        ],
        'sources' => [
            'label' => 'Sources',
            'description' => 'Knowledge base management',
            'category' => 'agent',
            'permissions' => [
                'agent.sources.train',
                'agent.sources.view.pdf',
                'agent.sources.view.word',
                'agent.sources.view.website',
                'agent.sources.view.qa',
                'agent.sources.view.text',
                'agent.sources.view.audio',
                'agent.sources.view.video',
                'agent.sources.view.youtube',
                'agent.sources.delete',
            ],
        ],
        'chat' => [
            'label' => 'Chat',
            'description' => 'Conversation management',
            'category' => 'agent',
            'permissions' => [
                'agent.chat.view',
                'agent.chat.takeover',
                'agent.chat.delete',
            ],
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'View performance data',
            'category' => 'agent',
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
            'category' => 'agent',
            'permissions' => [
                'agent.topics.add',
                'agent.topics.edit',
                'agent.topics.delete',
            ],
        ],
        'leads' => [
            'label' => 'Leads',
            'description' => 'Lead management',
            'category' => 'agent',
            'permissions' => [
                'agent.leads.view',
                'agent.leads.settings',
                'agent.leads.export',
            ],
        ],

        // Team/Account-level permissions
        'team' => [
            'label' => 'Team',
            'description' => 'Team-wide permissions',
            'category' => 'other',
            'permissions' => [
                'agents.create',
            ],
        ],
        'billing' => [
            'label' => 'Billing',
            'description' => 'Account billing',
            'category' => 'other',
            'permissions' => [
                'billing.view',
                'billing.manage',
            ],
        ],
    ],
];
