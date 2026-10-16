<?php

declare(strict_types=1);

use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;

/**
 * Authorization server metadata as discovered: a server must advertise S256
 * to count as supporting PKCE.
 */
it('supports pkce only when the server advertises S256', function (array $methods, bool $supported): void {
    $metadata = OAuthServerMetadata::fromArray('https://auth.example.test', [
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        ...$methods,
    ]);

    expect($metadata->supportsPkce())->toBe($supported);
})->with([
    'no pkce methods' => [[], false],
    'plain only' => [['code_challenge_methods_supported' => ['plain']], false],
    'S256' => [['code_challenge_methods_supported' => ['plain', 'S256']], true],
]);
