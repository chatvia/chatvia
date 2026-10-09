<?php

declare(strict_types=1);

return [

    // Languages the public site is served in — kept in lock-step with the dashboard
    // switcher (resources/js/i18n `LOCALES`). `en` is the default (config/app.php);
    // `ar` and `fa` are right-to-left. `flag` is the SVG basename under
    // public/images/flags/ used by the header language dropdown. Only locales with
    // a `lang/{code}/landing.php` file are actually translated — the rest resolve
    // and fall back to English (fallback_locale) until translations land.
    'supportedLocales' => [
        // Kept in lock-step with the dashboard switcher (resources/js/i18n `LOCALES`).
        // Every row here has a lang/{code}/landing.php file; `ar` and `fa` are
        // right-to-left. Remove a row to hide a language on the public site.
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

    // Browser auto-detection stays OFF (locale is resolved in
    // App\Http\Middleware\Web\ResolveLandingLocale, not the package). Guests land on the
    // English default and pick German or French from the switcher; letting the package
    // auto-detect would send a browser to a locale before the visitor chooses and set a
    // sticky session that fights the switcher.
    'useAcceptLanguageHeader' => false,

    // Every locale — including the default `en` — has a URL segment: the English
    // site is served under `/en` (e.g. `/en/pricing`), German under `/de`, and so
    // on. Combined with the `localizationRedirect` middleware, an un-prefixed URL
    // like `/` redirects to `/en`, so there is one canonical, locale-explicit URL
    // per page (no SEO duplicate content).
    'hideDefaultLocaleInURL' => false,

    // Order shown in the language dropdown — mirrors the dashboard switcher
    // (resources/js/i18n `LOCALES`): EU majors, EU/EEA Nordic + Polish, then RTL.
    'localesOrder' => ['en', 'de', 'fr', 'es', 'pt', 'pl', 'da', 'sv', 'fi', 'no', 'ar', 'fa'],

    'localesMapping' => [],

    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),

    // Paths that must never be locale-prefixed or redirected.
    'urlsIgnored' => [],

    // POST/PUT/PATCH/DELETE are never redirected — only GET navigation is localized,
    // so form submissions to non-localized action URLs are left untouched.
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
