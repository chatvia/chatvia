<?php

namespace Tests\Unit\Chat\Tooling\Mcp;

use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Chat\Services\Tooling\Mcp\McpClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class McpCatalogTest extends TestCase
{
    /**
     * Build a server that doesn't hit the database when save() is called —
     * the test only cares about catalog-shaping behavior, not persistence.
     */
    private function inMemoryServer(): AgentMcpServer
    {
        $server = new class extends AgentMcpServer
        {
            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $server->id = 7;
        $server->agent_id = 1;
        $server->slug = 'test';
        $server->name = 'Test';
        $server->url = 'https://mcp.example.test/rpc';
        $server->transport = 'streamable_http';
        $server->auth_type = 'bearer';
        $server->auth_value = 'tok';
        $server->headers = [];
        $server->timeout_ms = 5000;
        $server->enabled = true;
        $server->exists = false;

        return $server;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_empty_for_disabled_server(): void
    {
        $client = $this->createMock(McpClient::class);
        $client->expects($this->never())->method('fetchTools');

        $catalog = new McpCatalog($client);
        $server = $this->inMemoryServer();
        $server->enabled = false;

        $this->assertSame([], $catalog->for($server));
    }

    public function test_prefixes_tool_names_and_validates_shape(): void
    {
        $client = $this->createMock(McpClient::class);
        $client->method('fetchTools')->willReturn([
            ['name' => 'list_repos', 'description' => 'l', 'inputSchema' => ['type' => 'object']],
            // missing name — must be filtered
            ['description' => 'broken'],
            // invalid prefixed identifier — must be filtered
            ['name' => '/oops', 'description' => '', 'inputSchema' => []],
        ]);

        $tools = (new McpCatalog($client))->for($this->inMemoryServer());

        $this->assertCount(1, $tools);
        $this->assertSame('mcp__test__list_repos', $tools[0]['function']['name']);
        $this->assertSame('function', $tools[0]['type']);
    }

    public function test_caches_subsequent_calls(): void
    {
        $client = $this->createMock(McpClient::class);
        $client->expects($this->once()) // <-- proves we only fetch once
            ->method('fetchTools')
            ->willReturn([['name' => 'foo', 'description' => '', 'inputSchema' => []]]);

        $catalog = new McpCatalog($client);
        $server = $this->inMemoryServer();

        $first = $catalog->for($server);
        $second = $catalog->for($server);

        $this->assertSame($first, $second);
    }
}
