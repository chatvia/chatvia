<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Team Permissions
    |--------------------------------------------------------------------------
    |
    | Define all available permissions that can be assigned to team roles.
    | Permissions are organized by category for easier management.
    |
    */

    'permissions' => [
        // Agents
        'agents.view' => 'View agents',
        'agents.create' => 'Create agents',
        'agents.edit' => 'Edit agents',
        'agents.delete' => 'Delete agents',

        // Sources (in agent)
        'sources.view' => 'View sources',
        'sources.add' => 'Add sources',
        'sources.delete' => 'Delete sources',

        // Chat
        'chat.view' => 'View chat conversations',
        'chat.takeover' => 'Take over chat conversations',
        'chat.delete' => 'Delete chat conversations',

        // Analytics
        'analytics.chats' => 'View chat analytics',
        'analytics.metrics' => 'View metrics analytics',
        'analytics.sentiment' => 'View sentiment analytics',
        'analytics.tone' => 'View tone analytics',
        'analytics.topics' => 'View topics analytics',
        'analytics.sources' => 'View sources analytics',

        // Leads
        'leads.view' => 'View leads',
        'leads.settings' => 'Edit lead settings',
        'leads.export' => 'Export leads',

        // Billing
        'billing.view' => 'View billing & subscription',
        'billing.manage' => 'Manage subscription & payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    |
    | Define default roles that are created when a new team is created.
    | The 'owner' role is special and gets all permissions automatically.
    |
    */

    'roles' => [
        'owner' => [
            'name' => 'Owner',
            'description' => 'Full access to all team resources',
            'permissions' => ['*'], // Wildcard = all permissions
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Groups (for UI display)
    |--------------------------------------------------------------------------
    |
    | Group permissions for display in the UI when assigning roles.
    |
    */

    'groups' => [
        'agents' => [
            'label' => 'Agents',
            'permissions' => ['agents.view', 'agents.create', 'agents.edit', 'agents.delete'],
        ],
        'sources' => [
            'label' => 'Sources',
            'permissions' => ['sources.view', 'sources.add', 'sources.delete'],
        ],
        'chat' => [
            'label' => 'Chat',
            'permissions' => ['chat.view', 'chat.takeover', 'chat.delete'],
        ],
        'analytics' => [
            'label' => 'Analytics',
            'permissions' => [
                'analytics.chats',
                'analytics.metrics',
                'analytics.sentiment',
                'analytics.tone',
                'analytics.topics',
                'analytics.sources',
            ],
        ],
        'leads' => [
            'label' => 'Leads',
            'permissions' => ['leads.view', 'leads.settings', 'leads.export'],
        ],
        'billing' => [
            'label' => 'Billing',
            'permissions' => ['billing.view', 'billing.manage'],
        ],
    ],
];
