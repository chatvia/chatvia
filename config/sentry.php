<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sentry
|--------------------------------------------------------------------------
|
| Laravel merges these keys over the config/sentry.php of the
| sentry/sentry-laravel package, which keeps every other option. The DSN and
| the environment are set in the admin panel (Settings → Platform → Sentry).
|
*/

return [
    // No IP addresses, cookies or signed-in people in error reports.
    'send_default_pii' => false,

    // Chat messages, form answers and passwords travel in request bodies,
    // so an error report never carries one.
    'max_request_body_size' => 'none',
];
