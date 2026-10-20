<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PDF generation (spatie/laravel-pdf)
|--------------------------------------------------------------------------
|
| PDFs (the hosted chat's "Export as PDF", and the PDF of an office file for
| its summary) are made by WeasyPrint, a command-line program: no browser,
| and it lays out right-to-left scripts (Arabic, Persian) and CJK text
| correctly with the system fonts. A host that cannot install programs picks
| Cloudflare Browser Rendering or a Gotenberg server instead (Settings →
| Platform → PDF maker), which fill the keys below. Only the keys below
| differ from the package defaults; the rest are merged in.
|
*/

return [

    'driver' => 'weasyprint',

    'weasyprint' => [
        // A program name is looked up on the PATH of the process that runs
        // it. A full path is set in the admin panel (Settings → Platform →
        // Programs) when weasyprint lives somewhere else.
        'binary' => 'weasyprint',

        // Seconds. A long conversation takes a few seconds to lay out.
        'timeout' => 60,

        // Load nothing from the network or the disk: a chat message is AI
        // output, and an image link in it must not make the server fetch an
        // internal address. The template embeds its font as a data: URL.
        // The Chromium makers get the same rule from the pages' own
        // Content-Security-Policy.
        'allowed-protocols' => 'data',
    ],

    // Set in the admin panel, never in .env.
    'cloudflare' => [
        'api_token' => null,
        'account_id' => null,
    ],

    'gotenberg' => [
        'url' => null,
        'username' => null,
        'password' => null,
    ],

];
