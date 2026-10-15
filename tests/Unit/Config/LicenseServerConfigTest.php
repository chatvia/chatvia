<?php

/**
 * The license URLs come from CHATVIA_LICENSE_SERVER, read when the config
 * loads: chatvia.ai unless a developer points it at a local copy of the site.
 */
function licenseConfigWith(?string $server): mixed
{
    $old = [$_SERVER['CHATVIA_LICENSE_SERVER'] ?? null, $_ENV['CHATVIA_LICENSE_SERVER'] ?? null];

    if ($server === null) {
        unset($_SERVER['CHATVIA_LICENSE_SERVER'], $_ENV['CHATVIA_LICENSE_SERVER']);
    } else {
        $_SERVER['CHATVIA_LICENSE_SERVER'] = $_ENV['CHATVIA_LICENSE_SERVER'] = $server;
    }

    try {
        return (require base_path('config/chatvia.php'))['license'];
    } finally {
        [$_SERVER['CHATVIA_LICENSE_SERVER'], $_ENV['CHATVIA_LICENSE_SERVER']] = $old;
    }
}

it('sells and activates licenses at chatvia.ai by default', function (): void {
    expect(licenseConfigWith(null))->toMatchArray([
        'buy_url' => 'https://chatvia.ai/license',
        'manage_url' => 'https://chatvia.ai/license/manage',
        'api_url' => 'https://chatvia.ai/api/licenses',
    ]);
});

it('uses a local license site when one is set', function (): void {
    expect(licenseConfigWith('http://chatvia-landing.test'))->toMatchArray([
        'buy_url' => 'http://chatvia-landing.test/license',
        'manage_url' => 'http://chatvia-landing.test/license/manage',
        'api_url' => 'http://chatvia-landing.test/api/licenses',
    ]);
});
