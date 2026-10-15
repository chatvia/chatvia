<?php

declare(strict_types=1);

/**
 * Chatvia's own site comes from CHATVIA_URI, read when the config loads:
 * chatvia.ai unless a developer points it at a local copy of the site. The
 * license URLs are built from it.
 */
function chatviaConfigWith(?string $uri): mixed
{
    $old = [$_SERVER['CHATVIA_URI'] ?? null, $_ENV['CHATVIA_URI'] ?? null];

    if ($uri === null) {
        unset($_SERVER['CHATVIA_URI'], $_ENV['CHATVIA_URI']);
    } else {
        $_SERVER['CHATVIA_URI'] = $_ENV['CHATVIA_URI'] = $uri;
    }

    try {
        return require base_path('config/chatvia.php');
    } finally {
        // Put the variable back as it was, absent when it was absent.
        [$server, $env] = $old;

        if ($server === null) {
            unset($_SERVER['CHATVIA_URI']);
        } else {
            $_SERVER['CHATVIA_URI'] = $server;
        }

        if ($env === null) {
            unset($_ENV['CHATVIA_URI']);
        } else {
            $_ENV['CHATVIA_URI'] = $env;
        }
    }
}

it('points at chatvia.ai by default', function (): void {
    $config = chatviaConfigWith(null);

    expect($config['license'])->toMatchArray([
        'buy_url' => 'https://chatvia.ai/license',
        'manage_url' => 'https://chatvia.ai/license/manage',
        'api_url' => 'https://chatvia.ai/api/licenses',
    ]);
});

it('points at a local copy of the site when one is set', function (): void {
    $config = chatviaConfigWith('http://chatvia-landing.test');

    expect($config['license'])->toMatchArray([
        'buy_url' => 'http://chatvia-landing.test/license',
        'manage_url' => 'http://chatvia-landing.test/license/manage',
        'api_url' => 'http://chatvia-landing.test/api/licenses',
    ]);
});
