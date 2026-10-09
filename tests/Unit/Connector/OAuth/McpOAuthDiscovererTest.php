<?php

namespace Tests\Unit\Connector\OAuth;

use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpOAuthDiscovererTest extends TestCase
{
    public function test_follows_www_authenticate_to_protected_resource_to_authorization_server(): void
    {
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

        $this->assertSame('https://auth.example.test', $metadata->authServerUrl);
        $this->assertSame('https://auth.example.test/authorize', $metadata->authorizationEndpoint);
        $this->assertSame('https://auth.example.test/token', $metadata->tokenEndpoint);
        $this->assertSame('https://auth.example.test/register', $metadata->registrationEndpoint);
        $this->assertTrue($metadata->supportsPkce());
        $this->assertTrue($metadata->supportsDynamicRegistration());
    }

    public function test_falls_back_to_conventional_path_when_www_authenticate_is_absent(): void
    {
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

        $this->assertSame('https://auth.example.test', $metadata->authServerUrl);
    }

    public function test_throws_when_no_authorization_servers_advertised(): void
    {
        Http::fake([
            'mcp.example.test/rpc' => Http::response('', 401),
            'mcp.example.test/.well-known/oauth-protected-resource' => Http::response([
                'resource' => 'https://mcp.example.test/rpc',
            ]),
        ]);

        $this->expectException(McpOAuthException::class);

        (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc');
    }

    public function test_rejects_protected_resource_metadata_for_another_address(): void
    {
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

        $this->expectException(McpOAuthException::class);
        $this->expectExceptionMessage('different address');

        (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc');
    }

    public function test_accepts_a_resource_that_is_the_origin_or_differs_only_by_trailing_slash(): void
    {
        foreach (['https://mcp.example.test', 'https://MCP.example.test:443/', 'https://mcp.example.test/rpc/'] as $resource) {
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

            $this->assertSame('https://auth.example.test', $metadata->authServerUrl, $resource);
        }
    }

    public function test_rejects_a_sibling_path_on_the_same_origin(): void
    {
        Http::fake([
            'mcp.example.test/rpc' => Http::response('', 401),
            'mcp.example.test/.well-known/*' => Http::response([
                'resource' => 'https://mcp.example.test/rpc-admin',
                'authorization_servers' => ['https://auth.example.test'],
            ]),
        ]);

        $this->expectException(McpOAuthException::class);

        (new McpOAuthDiscoverer)->discover('https://mcp.example.test/rpc');
    }

    public function test_metadata_without_pkce_methods_counts_as_no_pkce(): void
    {
        $metadata = OAuthServerMetadata::fromArray('https://auth.example.test', [
            'authorization_endpoint' => 'https://auth.example.test/authorize',
            'token_endpoint' => 'https://auth.example.test/token',
        ]);

        $this->assertFalse($metadata->supportsPkce());
    }

    public function test_keeps_the_last_transport_failure_as_the_previous_exception(): void
    {
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
            $this->assertNotNull($e->getPrevious());
            $this->assertStringContainsString('connection refused', $e->getPrevious()->getMessage());
        }
    }
}
