<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PDF generation (spatie/laravel-pdf)
|--------------------------------------------------------------------------
|
| PDFs (the hosted chat's "Export as PDF") are made by WeasyPrint, a
| command-line program: no browser, and it lays out right-to-left scripts
| (Arabic, Persian) and CJK text correctly with the system fonts. Only the
| keys below differ from the package defaults; the rest are merged in.
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
        'allowed-protocols' => 'data',
    ],

];
