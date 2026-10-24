<?php

declare(strict_types=1);

use Tests\Support\Config\ConfigFile;

/**
 * Chatvia's own site comes from CHATVIA_URI, read when the config loads:
 * chatvia.ai unless a developer points it at a local copy of the site. The
 * license URLs are built from it.
 */
it('points at chatvia.ai by default', function (): void {
    $config = ConfigFile::with('chatvia.php', ['CHATVIA_URI' => null]);

    expect($config['license'])->toMatchArray([
        'buy_url' => 'https://chatvia.ai/license',
        'manage_url' => 'https://chatvia.ai/license/manage',
        'api_url' => 'https://chatvia.ai/api/licenses',
    ]);
});

it('points at a local copy of the site when one is set', function (): void {
    $config = ConfigFile::with('chatvia.php', ['CHATVIA_URI' => 'http://chatvia-landing.test']);

    expect($config['license'])->toMatchArray([
        'buy_url' => 'http://chatvia-landing.test/license',
        'manage_url' => 'http://chatvia-landing.test/license/manage',
        'api_url' => 'http://chatvia-landing.test/api/licenses',
    ]);
});
