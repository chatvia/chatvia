<?php

use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Discovery walks from the MCP endpoint to its authorization server: the
 * `WWW-Authenticate` hint or the conventional well-known path, a protected
 * resource document that must describe this endpoint, then the server's
 * metadata.
 */
it('follows www-authenticate to the protected resource and on to the authorization server', function (): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401, [
            'WWW-Authenticate' => 'Bearer resource_metadata="https://mcp.example.test/.well-known/oauth-protected-resource"',
        ]),
        'mcp.example.test/.well-known/oauth-protected-resource' => Http::response([
            'resource' => 'https://mcp.example.test/rpc',
            'authorization_servers' => ['https://auth.example.test'],
        ]),
        'auth.example.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.example.test',
            'authorization_endpoint' => 'https://auth.example.test/authorize',
            'token_endpoint' => 'https://auth.example.test/token',
            'registration_endpoint' => 'https://auth.example.test/register',
            'code_challenge_methods_supported' => ['S256'],
        ]),
    ]);

    $metadata = (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc');

    expect($metadata->authServerUrl)->toBe('https://auth.example.test')
        ->and($metadata->authorizationEndpoint)->toBe('https://auth.example.test/authorize')
        ->and($metadata->tokenEndpoint)->toBe('https://auth.example.test/token')
        ->and($metadata->registrationEndpoint)->toBe('https://auth.example.test/register')
        ->and($metadata->supportsPkce())->toBeTrue()
        ->and($metadata->supportsDynamicRegistration())->toBeTrue();
});

it('falls back to the conventional path when www-authenticate is absent', function (): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401),
        'mcp.example.test/.well-known/oauth-protected-resource' => Http::response([
            'authorization_servers' => ['https://auth.example.test'],
        ]),
        'auth.example.test/.well-known/oauth-authorization-server' => Http::response([
            'authorization_endpoint' => 'https://auth.example.test/authorize',
            'token_endpoint' => 'https://auth.example.test/token',
        ]),
    ]);

    $metadata = (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc');

    expect($metadata->authServerUrl)->toBe('https://auth.example.test');
});

it('throws when no authorization server is advertised', function (): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401),
        'mcp.example.test/.well-known/oauth-protected-resource' => Http::response([
            'resource' => 'https://mcp.example.test/rpc',
        ]),
    ]);

    expect(fn (): OAuthServerMetadata => (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc'))
        ->toThrow(McpOAuthException::class, 'did not list authorization_servers');
});

it('rejects protected resource metadata for another address', function (): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401, [
            'WWW-Authenticate' => 'Bearer resource_metadata="https://mcp.example.test/.well-known/oauth-protected-resource"',
        ]),
        // Every candidate claims to describe a different service.
        'mcp.example.test/.well-known/*' => Http::response([
            'resource' => 'https://other-service.example.test/mcp',
            'authorization_servers' => ['https://auth.example.test'],
        ]),
    ]);

    expect(fn (): OAuthServerMetadata => (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc'))
        ->toThrow(McpOAuthException::class, 'different address');
});

it('accepts a resource that is the origin or differs only by a trailing slash', function (string $resource): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401),
        'mcp.example.test/.well-known/oauth-protected-resource/rpc' => Http::response([
            'resource' => $resource,
            'authorization_servers' => ['https://auth.example.test'],
        ]),
        'auth.example.test/.well-known/oauth-authorization-server' => Http::response([
            'authorization_endpoint' => 'https://auth.example.test/authorize',
            'token_endpoint' => 'https://auth.example.test/token',
        ]),
    ]);

    $metadata = (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc');

    expect($metadata->authServerUrl)->toBe('https://auth.example.test');
})->with([
    'the origin' => ['https://mcp.example.test'],
    'the origin with case, port and slash' => ['https://MCP.example.test:443/'],
    'the endpoint with a trailing slash' => ['https://mcp.example.test/rpc/'],
]);

it('rejects a sibling path on the same origin', function (): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401),
        'mcp.example.test/.well-known/*' => Http::response([
            'resource' => 'https://mcp.example.test/rpc-admin',
            'authorization_servers' => ['https://auth.example.test'],
        ]),
    ]);

    // The message proves the resource check refused it: any later failure
    // (such as the unfaked authorization server) is an McpOAuthException too.
    expect(fn (): OAuthServerMetadata => (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc'))
        ->toThrow(McpOAuthException::class, 'different address');
});

it('keeps the last transport failure as the previous exception', function (): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401),
        'mcp.example.test/.well-known/*' => Http::response([
            'authorization_servers' => ['https://auth.example.test'],
        ]),
        'auth.example.test/*' => fn () => throw new ConnectionException('connection refused'),
    ]);

    try {
        (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc');
        $this->fail('Expected McpOAuthException');
    } catch (McpOAuthException $e) {
        expect($e->getPrevious())->not->toBeNull()
            ->and($e->getPrevious()->getMessage())->toContain('connection refused');
    }
});
