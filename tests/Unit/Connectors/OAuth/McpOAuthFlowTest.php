<?php

namespace Tests\Unit\Connectors\OAuth;

use Domain\Connectors\Data\ConnectorData;
use Domain\Agents\Models\Agent;
use Domain\Connectors\Models\ConnectorOAuthClient;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Connectors\Services\OAuth\McpOAuthDiscoverer;
use Domain\Connectors\Services\OAuth\McpOAuthException;
use Domain\Connectors\Services\OAuth\McpOAuthFlow;
use Domain\Connectors\Services\OAuth\McpOAuthRegistrar;
use Domain\Connectors\Services\OAuth\OAuthServerMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class McpOAuthFlowTest extends TestCase
{
    private function metadata(): OAuthServerMetadata
    {
        return OAuthServerMetadata::fromArray('https://auth.example.test', [
            'authorization_endpoint' => 'https://auth.example.test/authorize',
            'token_endpoint' => 'https://auth.example.test/token',
            'registration_endpoint' => 'https://auth.example.test/register',
            'code_challenge_methods_supported' => ['S256'],
        ]);
    }

    private function connector(): ConnectorData
    {
        return ConnectorData::fromArray([
            'slug' => 'fixture',
            'name' => 'Fixture',
            'description' => 'd',
            'category' => 'Test',
            'icon' => 'fixture',
            'brand_color' => '000000',
            'auth_strategy' => 'oauth',
            'mcp_url' => 'https://mcp.example.test/rpc',
        ]);
    }

    private function agent(): Agent
    {
        $agent = new Agent;
        $agent->id = 7;
        $agent->exists = true;

        return $agent;
    }

    private function flowWithStubs(McpCatalog|MockObject $catalog): McpOAuthFlow
    {
        $discoverer = $this->createMock(McpOAuthDiscoverer::class);
        $discoverer->method('discover')->willReturn($this->metadata());

        $registrar = $this->createMock(McpOAuthRegistrar::class);
        $registrar->method('redirectUri')->willReturn('https://chatvia.test/connectors/oauth/callback');

        // Always present a pre-registered client (skipping the actual DCR
        // network call) — the registrar test handles DCR coverage.
        $client = new ConnectorOAuthClient;
        $client->id = 1;
        $client->auth_server_url = 'https://auth.example.test';
        $client->client_id = 'oauth-client-xyz';
        $client->client_secret = null;
        $client->token_endpoint = 'https://auth.example.test/token';
        $client->authorization_endpoint = 'https://auth.example.test/authorize';
        $client->exists = true;
        $registrar->method('clientFor')->willReturn($client);

        return new McpOAuthFlow($discoverer, $registrar, $catalog);
    }

    public function test_begin_returns_authorize_url_with_pkce_and_caches_state(): void
    {
        Cache::flush();

        $catalog = $this->createMock(McpCatalog::class);
        $flow = $this->flowWithStubs($catalog);

        $url = $flow->begin($this->agent(), $this->connector());

        $this->assertStringStartsWith('https://auth.example.test/authorize?', $url);

        $parts = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parts);

        $this->assertSame('code', $parts['response_type']);
        $this->assertSame('oauth-client-xyz', $parts['client_id']);
        $this->assertSame('S256', $parts['code_challenge_method']);
        $this->assertSame('https://chatvia.test/connectors/oauth/callback', $parts['redirect_uri']);
        $this->assertNotEmpty($parts['state']);
        $this->assertNotEmpty($parts['code_challenge']);

        // State is stashed for later code exchange.
        $this->assertIsArray(Cache::get('oauth:mcp:state:'.$parts['state']));
    }

    public function test_complete_rejects_unknown_state(): void
    {
        Cache::flush();

        $catalog = $this->createMock(McpCatalog::class);
        $flow = $this->flowWithStubs($catalog);

        $this->expectException(McpOAuthException::class);

        $flow->complete('not-a-real-state', 'some-code');
    }

    public function test_refresh_swaps_in_a_new_access_token(): void
    {
        $client = new ConnectorOAuthClient;
        $client->id = 1;
        $client->auth_server_url = 'https://auth.example.test';
        $client->client_id = 'oauth-client-xyz';
        $client->client_secret = null;
        $client->token_endpoint = 'https://auth.example.test/token';
        $client->authorization_endpoint = 'https://auth.example.test/authorize';
        $client->exists = true;

        $hijacked = new class extends \Domain\Agents\Models\AgentMcpServer
        {
            public bool $saved = false;

            public function save(array $options = []): bool
            {
                $this->saved = true;

                return true;
            }
        };
        $hijacked->id = 42;
        $hijacked->agent_id = 7;
        $hijacked->name = 'Fixture';
        $hijacked->slug = 'fixture';
        $hijacked->url = 'https://mcp.example.test/rpc';
        $hijacked->transport = 'streamable_http';
        $hijacked->auth_type = 'bearer';
        $hijacked->auth_value = 'old-token';
        $hijacked->oauth_authorization_server = 'https://auth.example.test';
        $hijacked->oauth_token_endpoint = 'https://auth.example.test/token';
        $hijacked->oauth_refresh_token = 'old-rt';
        $hijacked->oauth_expires_at = now()->subSecond();
        $hijacked->timeout_ms = 30000;
        $hijacked->enabled = true;

        Http::fake([
            'auth.example.test/token' => Http::response([
                'access_token' => 'fresh-token',
                'refresh_token' => 'fresh-rt',
                'expires_in' => 3600,
                'scope' => 'read',
            ]),
        ]);

        $registrar = $this->createMock(McpOAuthRegistrar::class);
        $registrar->method('findByAuthServer')->willReturn($client);

        $flow = new McpOAuthFlow(
            $this->createMock(McpOAuthDiscoverer::class),
            $registrar,
            $this->createMock(McpCatalog::class),
        );

        $flow->refresh($hijacked);

        $this->assertSame('fresh-token', $hijacked->auth_value);
        $this->assertSame('fresh-rt', $hijacked->oauth_refresh_token);
        $this->assertTrue($hijacked->saved);
    }
}
