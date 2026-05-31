<?php

return [
    'script_url_embed' => env('WIDGET_SCRIPT_URL', 'http://localhost:3000/dist/embed.js'),

    'chatvia_widget_id' => env('CHATVIA_WIDGET_ID'),

    'available_languages' => [
        'auto' => 'Auto',
        'en' => 'English',
        'ar' => 'Arabic (العربية)',
        'da' => 'Danish (Dansk)',
        'de' => 'German (Deutsch)',
        'es' => 'Spanish (Español)',
        'fr' => 'French (Français)',
        'pt' => 'Portuguese (Português)',
    ],
];
