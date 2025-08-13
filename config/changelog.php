<?php

return [
    'versions' => [
        [
            'version' => '13-08-2025',
            'release_date' => 'Aug 13, 2025',
            'status' => 'Latest',
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
