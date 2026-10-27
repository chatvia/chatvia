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

it('reads back the sealed state, which keeps its secrets out of plain sight', function (): void {
    $sealed = posState()->seal();

    expect($sealed)->not->toContain('verifier')->not->toContain('shh')
        ->and(PendingOAuthStateData::unseal($sealed))->toEqual(posState());
});

it('reads nothing back from a plain array, garbage, nothing, or a state without a binding', function (mixed $payload): void {
    expect(PendingOAuthStateData::unseal($payload))->toBeNull();
})->with([
    'a plain array' => [posState()->toArray()],
    'not encrypted' => ['not-encrypted'],
    'nothing' => [null],
    'no session binding' => fn (): string => Crypt::encryptString((string) json_encode([...posState()->toArray(), 'session_binding_hash' => null])),
]);

it('matches only the browser session that started the flow', function (): void {
    $state = posState('browser-secret');

    expect($state->matchesSession('browser-secret'))->toBeTrue()
        ->and($state->matchesSession('another-browser'))->toBeFalse()
        ->and($state->matchesSession(''))->toBeFalse();
});
