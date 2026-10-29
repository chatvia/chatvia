<?php

use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadataData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Illuminate\Support\Facades\Http;

/**
 * Discovery walks from the MCP endpoint to its authorization server: the
 * `WWW-Authenticate` hint or the conventional well-known path, a protected
 * resource document that must describe this endpoint (its origin, or the
 * endpoint itself up to case, port and a trailing slash), then the server's
 * metadata. A document for another address, or one that names no
 * authorization server, stops the flow.
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

it('accepts a resource at the conventional path that is the origin or the endpoint with a trailing slash', function (string $resource): void {
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
    'the origin with case, port and slash' => ['https://MCP.example.test:443/'],
    'the endpoint with a trailing slash' => ['https://mcp.example.test/rpc/'],
]);

it('refuses a protected resource document that names no authorization server or another address', function (array $document, string $message): void {
    Http::fake([
        'mcp.example.test/rpc' => Http::response('', 401),
        'mcp.example.test/.well-known/*' => Http::response($document),
    ]);

    expect(fn (): OAuthServerMetadataData => (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc'))
        ->toThrow(McpOAuthException::class, $message);
})->with([
    'no authorization server' => [['resource' => 'https://mcp.example.test/rpc'], 'did not list authorization_servers'],
    'no resource for an address the workspace typed' => [['authorization_servers' => ['https://auth.example.test']], 'does not say which address it protects'],
    'another service' => [['resource' => 'https://other-service.example.test/mcp', 'authorization_servers' => ['https://auth.example.test']], 'different address'],
    'a sibling path on the same origin' => [['resource' => 'https://mcp.example.test/rpc-admin', 'authorization_servers' => ['https://auth.example.test']], 'different address'],
]);
