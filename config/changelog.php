<?php

return [
    'versions' => [
        [
            'version' => '08-09-2025',
            'release_date' => 'Sep 8, 2025',
            'status' => 'Latest',
            'status_color' => 'blue',
            'changes' => [
                'new_features' => [
                    'New modern design with improved user experience',
                    'Chat logs - view and manage all conversations with your agents',
                    'Lead collection - capture visitor information through chat widgets',
                    'Enhanced widget customization options with color themes and positioning',
                ],
                'improvements' => [
                    'Better widget performance and responsiveness',
                    'Improved chat interface with better message handling',
                ],
            ],
        ],
        [
            'version' => '31-08-2025',
            'release_date' => 'Aug 31, 2025',
            'status' => 'Released',
            'status_color' => 'blue',
            'changes' => [
                'new_features' => [
                    'Chat widget for your website',
                    'User interface refinements with additional customization options',
                ],
            ],
        ],
        [
            'version' => '22-08-2025',
            'release_date' => 'Aug 22, 2025',
            'status' => 'Released',
            'status_color' => 'blue',
            'changes' => [
                'new_features' => [
                    'Chat preview added directly in the agent control panel, users can now chat with the agent without leaving the panel',
                    'Embedding via iframe is now supported, easily integrate ChatVia into your own website',
                    'User interface refinements with additional customization options',
                    'New dedicated tab for Security settings',
                    'New dedicated tab for Persona management',
                    'Performance improvements',
                ],
            ],
        ],
        [
            'version' => '14-08-2025',
            'release_date' => 'Aug 14, 2025',
            'status' => 'Released',
            'status_color' => 'blue',
            'changes' => [
                'new_features' => [
                    'Added support for Right-to-Left (RTL) languages',
                    'Added Arabic font',
                ],
            ],
        ],
        [
            'version' => '13-08-2025',
            'release_date' => 'Aug 13, 2025',
            'status' => 'Released',
            'status_color' => 'blue',
            'changes' => [
                'new_features' => [
                    'Added LaTeX rendering for math equations',
                ],
                'improvements' => [
                    'Better support for PDFs with complex layouts',
                    'Better support for files with multiple languages',
                ],
                'bug_fixes' => [
                    'Fixed a bug with PDF parsing and extraction',
                ],
            ],
        ],
        [
            'version' => '11-08-2025',
            'release_date' => 'Aug 11, 2025',
            'status' => 'Initial Release',
            'status_color' => 'gray',
            'changes' => [
                'core_features' => [
                    'Initial release of Chatvia',
                ],
            ],
        ],
    ],

    'change_types' => [
        'new_features' => [
            'label' => 'New Features',
            'color' => 'green',
            'icon' => '✓',
        ],
        'improvements' => [
            'label' => 'Improvements',
            'color' => 'blue',
            'icon' => '↑',
        ],
        'bug_fixes' => [
            'label' => 'Bug Fixes',
            'color' => 'yellow',
            'icon' => '🔧',
        ],
        'breaking_changes' => [
            'label' => 'Breaking Changes',
            'color' => 'red',
            'icon' => '⚠️',
        ],
        'core_features' => [
            'label' => 'Core Features',
            'color' => 'green',
            'icon' => '✓',
        ],
    ],
];
