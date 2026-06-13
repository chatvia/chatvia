<?php

return [

    // Languages the public site is served in — kept in lock-step with the dashboard
    // switcher (resources/js/i18n `LOCALES`). `en` is the default (config/app.php);
    // `ar` and `fa` are right-to-left. `flag` is the SVG basename under
    // public/images/flags/ used by the header language dropdown. Only locales with
    // a `lang/{code}/landing.php` file are actually translated — the rest resolve
    // and fall back to English (fallback_locale) until translations land.
    'supportedLocales' => [
        // Public marketing site is intentionally limited to EN/DE/FR for now — the
        // EU-sovereignty positioning is fully translated only in these three. The
        // other locales (ar, da, es, fa, pt, …) and their lang/*/landing.php files
        // stay on disk; re-add a row here to re-enable one in the future.
        'en' => ['name' => 'English', 'script' => 'Latn', 'native' => 'English',  'regional' => 'en_GB', 'flag' => 'gb'],
        'de' => ['name' => 'German',  'script' => 'Latn', 'native' => 'Deutsch',  'regional' => 'de_DE', 'flag' => 'de'],
        'fr' => ['name' => 'French',  'script' => 'Latn', 'native' => 'Français', 'regional' => 'fr_FR', 'flag' => 'fr'],
    ],

    // Browser auto-detection stays OFF (locale is resolved in
    // App\Http\Middleware\Web\ResolveLandingLocale, not the package). Guests land on the
    // English default and pick German or French from the switcher; letting the package
    // auto-detect would send a browser to a locale before the visitor chooses and set a
    // sticky session that fights the switcher.
    'useAcceptLanguageHeader' => false,

    // The default locale (`en`) has no URL segment: `/` IS the English site, while
    // every other locale is served under `/{code}` (e.g. `/ar`, `/fr`). Combined
    // with the `localizationRedirect` middleware, `/en/...` redirects to `/...` so
    // there is one canonical URL per page (no SEO duplicate content).
    'hideDefaultLocaleInURL' => true,

    // Order shown in the language dropdown — mirrors the dashboard switcher.
    'localesOrder' => ['en', 'de', 'fr'],

    'localesMapping' => [],

    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),

    // Paths that must never be locale-prefixed or redirected.
    'urlsIgnored' => [],

    // POST/PUT/PATCH/DELETE are never redirected — only GET navigation is localized,
    // so form submissions to non-localized action URLs are left untouched.
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
