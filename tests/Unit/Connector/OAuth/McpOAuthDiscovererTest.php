<?php

namespace Tests\Unit\Connector\OAuth;

use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
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
}
