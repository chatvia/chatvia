<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Page names are paths from resources/js without the extension
    | ("dashboard/agents/pages/Persona"), so pages are looked up from there.
    | `assertInertia` checks that a rendered page exists on disk.
    |
    */

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js')],
        'extensions' => ['ts', 'vue'],
    ],

];
