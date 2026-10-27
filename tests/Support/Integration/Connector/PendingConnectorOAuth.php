<?php

declare(strict_types=1);

namespace Tests\Support\Integration\Connector;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadataData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthStateData;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Uri;

/**
 * A connector OAuth flow in flight: an authorization server stubbed at its
 * discovery and client registration, the sealed pending state cached the way
 * BeginConnectorOAuthAction caches it (for a flow started by a user in a
 * browser session that holds $binding), and that state read back from the
 * authorize URL a flow returns.
 */
final class PendingConnectorOAuth
{
    private const string CACHE_PREFIX = 'oauth:mcp:state:';

    /**
     * Cache the pending state $state. A null connector slug is a custom
     * server, which carries its own name and slug.
     */
    public static function stash(string $state, User $starter, string $binding, ?string $connectorSlug = 'notion'): void
    {
        $pending = new PendingOAuthStateData(
            userId: $starter->id,
            connectorSlug: $connectorSlug,
            authServerUrl: 'https://auth.example.test',
            tokenEndpoint: 'https://auth.example.test/token',
            clientId: 'client-123',
            codeVerifier: 'verifier',
            mcpUrl: 'https://mcp.example.test/rpc',
            sessionBindingHash: PendingOAuthStateData::hashBinding($binding),
            customName: $connectorSlug === null ? 'My server' : null,
            customSlug: $connectorSlug === null ? 'my_server' : null,
        );

        Cache::put(self::CACHE_PREFIX.$state, $pending->seal(), 600);
    }

    /** The pending state cached for the `state` of an authorize URL. */
    public static function fromAuthorizeUrl(string $url): ?PendingOAuthStateData
    {
        return PendingOAuthStateData::unseal(self::sealedFor($url));
    }

    /** The sealed form of that state, as the cache holds it. */
    public static function sealedFor(string $url): mixed
    {
        return Cache::get(self::CACHE_PREFIX.Uri::of($url)->query()->get('state'));
    }

    /**
     * Stub discovery (answering with $metadata, by default an S256 PKCE
     * server) and client registration (handing out a known client), so a flow
     * never reaches the network.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function stubAuthorizationServer(?array $metadata = null, ?string $clientSecret = null): ConnectorOAuthClient
    {
        test()->mock(McpOAuthDiscoverer::class)->shouldReceive('discover')
            ->andReturn(OAuthServerMetadataData::fromArray('https://auth.example.test', $metadata ?? self::pkceMetadata()));

        $client = new ConnectorOAuthClient()->forceFill([
            'auth_server_url' => 'https://auth.example.test',
            'client_id' => 'oauth-client-xyz',
            'client_secret' => $clientSecret,
            'token_endpoint' => 'https://auth.example.test/token',
            'authorization_endpoint' => 'https://auth.example.test/authorize',
        ]);

        $registrar = test()->mock(McpOAuthRegistrar::class);
        $registrar->shouldReceive('clientFor')->andReturn($client);
        $registrar->shouldReceive('redirectUri')->andReturn('https://chatvia.test/connectors/oauth/callback');

        return $client;
    }

    /** @return array<string, mixed> metadata of a server that supports S256 PKCE */
    public static function pkceMetadata(): array
    {
        return [
            'authorization_endpoint' => 'https://auth.example.test/authorize',
            'token_endpoint' => 'https://auth.example.test/token',
            'code_challenge_methods_supported' => ['S256'],
        ];
    }
}
