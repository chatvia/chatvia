<?php

use Domain\Integration\Connector\Data\OAuth\PendingOAuthStateData;
use Illuminate\Support\Facades\Crypt;

/**
 * The OAuth state cached between the authorize redirect and the callback. It
 * holds the PKCE verifier (and maybe a client secret), so only its encrypted
 * form is read back, and it is tied to the browser session that started it.
 */
function posState(string $binding = 'browser-secret'): PendingOAuthStateData
{
    return new PendingOAuthStateData(
        userId: 5,
        connectorSlug: 'notion',
        authServerUrl: 'https://auth.example.test',
        tokenEndpoint: 'https://auth.example.test/token',
        clientId: 'client-123',
        codeVerifier: 'verifier',
        mcpUrl: 'https://mcp.example.test/rpc',
        sessionBindingHash: PendingOAuthStateData::hashBinding($binding),
        clientSecret: 'shh',
    );
}

it('reads back only a sealed state with a binding, which keeps its secrets out of plain sight', function (): void {
    $sealed = posState()->seal();
    $unbound = Crypt::encryptString((string) json_encode([...posState()->toArray(), 'session_binding_hash' => null]));

    expect($sealed)->not->toContain('verifier')->not->toContain('shh')
        ->and(PendingOAuthStateData::unseal($sealed))->toEqual(posState())
        ->and(PendingOAuthStateData::unseal(posState()->toArray()))->toBeNull()
        ->and(PendingOAuthStateData::unseal('not-encrypted'))->toBeNull()
        ->and(PendingOAuthStateData::unseal(null))->toBeNull()
        ->and(PendingOAuthStateData::unseal($unbound))->toBeNull();
});

it('matches only the browser session that started the flow', function (): void {
    $state = posState('browser-secret');

    expect($state->matchesSession('browser-secret'))->toBeTrue()
        ->and($state->matchesSession('another-browser'))->toBeFalse()
        ->and($state->matchesSession(''))->toBeFalse();
});
