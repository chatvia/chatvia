<?php

declare(strict_types=1);

return [

    // The locale segments the app's URLs accept (`/en/dashboard`, `/de/…`). `en`
    // is the default (config/app.php); `ar` and `fa` are right-to-left. `flag` is
    // the SVG basename under public/images/flags/. The language the dashboard is
    // shown in follows the user's saved `users.locale`, not this list.
    'supportedLocales' => [
        'en' => ['name' => 'English',    'script' => 'Latn', 'native' => 'English',  'regional' => 'en_GB', 'flag' => 'gb'],
        'de' => ['name' => 'German',     'script' => 'Latn', 'native' => 'Deutsch',  'regional' => 'de_DE', 'flag' => 'de'],
        'fr' => ['name' => 'French',     'script' => 'Latn', 'native' => 'Français', 'regional' => 'fr_FR', 'flag' => 'fr'],
        'es' => ['name' => 'Spanish',    'script' => 'Latn', 'native' => 'Español',  'regional' => 'es_ES', 'flag' => 'es'],
        'pt' => ['name' => 'Portuguese', 'script' => 'Latn', 'native' => 'Português', 'regional' => 'pt_PT', 'flag' => 'pt'],
        'pl' => ['name' => 'Polish',     'script' => 'Latn', 'native' => 'Polski',   'regional' => 'pl_PL', 'flag' => 'pl'],
        'da' => ['name' => 'Danish',     'script' => 'Latn', 'native' => 'Dansk',    'regional' => 'da_DK', 'flag' => 'dk'],
        'sv' => ['name' => 'Swedish',    'script' => 'Latn', 'native' => 'Svenska',  'regional' => 'sv_SE', 'flag' => 'se'],
        'fi' => ['name' => 'Finnish',    'script' => 'Latn', 'native' => 'Suomi',    'regional' => 'fi_FI', 'flag' => 'fi'],
        'no' => ['name' => 'Norwegian',  'script' => 'Latn', 'native' => 'Norsk',    'regional' => 'nb_NO', 'flag' => 'no'],
        'ar' => ['name' => 'Arabic',     'script' => 'Arab', 'native' => 'العربية',  'regional' => 'ar_SA', 'flag' => 'iq'],
        'fa' => ['name' => 'Persian',    'script' => 'Arab', 'native' => 'فارسی',    'regional' => 'fa_IR', 'flag' => 'ir'],
    ],

    // Browser auto-detection stays OFF: guests land on the English default and
    // pick a language themselves; auto-detection would set a sticky session that
    // fights the user's saved preference.
    'useAcceptLanguageHeader' => false,

    // Every locale — including the default `en` — has a URL segment: the
    // dashboard is served under `/en/dashboard`, `/de/dashboard`, and so on.
    'hideDefaultLocaleInURL' => false,

    // EU majors, EU/EEA Nordic + Polish, then RTL.
    'localesOrder' => ['en', 'de', 'fr', 'es', 'pt', 'pl', 'da', 'sv', 'fi', 'no', 'ar', 'fa'],

    'localesMapping' => [],

    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),

    // Paths that must never be locale-prefixed or redirected.
    'urlsIgnored' => [],

    // POST/PUT/PATCH/DELETE are never redirected — only GET navigation is localized,
    // so form submissions to non-localized action URLs are left untouched.
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
