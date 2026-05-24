<?php

namespace Tests\Unit\Chat\Tooling\Mcp;

use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpClient;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpClientTest extends TestCase
{
    private function server(): AgentMcpServer
    {
        $server = new AgentMcpServer;
        $server->id = 99;
        $server->agent_id = 1;
        $server->name = 'Test';
        $server->slug = 'test';
        $server->url = 'https://mcp.example.test/rpc';
        $server->transport = 'streamable_http';
        $server->auth_type = 'bearer';
        $server->auth_value = 'tok-abc';
        $server->headers = [];
        $server->timeout_ms = 5000;
        $server->enabled = true;

        return $server;
    }

    public function test_fetch_tools_returns_server_catalog(): void
    {
        Http::fake(function ($request) {
            $body = $request->data();
            $method = $body['method'] ?? '';

            if ($method === 'initialize') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => [
                        'protocolVersion' => '2025-03-26',
                        'capabilities' => (object) [],
                        'serverInfo' => ['name' => 'fixture', 'version' => '1'],
                    ],
                ], 200, ['Mcp-Session-Id' => 's-1']);
            }

            if ($method === 'notifications/initialized') {
                return Http::response('', 202);
            }

            if ($method === 'tools/list') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => [
                        'tools' => [
                            ['name' => 'foo', 'description' => 'fixture', 'inputSchema' => ['type' => 'object']],
                        ],
                    ],
                ]);
            }

            return Http::response('', 200);
        });

        $tools = (new McpClient)->fetchTools($this->server());

        $this->assertCount(1, $tools);
        $this->assertSame('foo', $tools[0]['name']);
    }

    public function test_call_tool_returns_result_envelope(): void
    {
        Http::fake(function ($request) {
            $body = $request->data();
            $method = $body['method'] ?? '';

            if ($method === 'initialize') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => ['protocolVersion' => '2025-03-26'],
                ], 200, ['Mcp-Session-Id' => 's']);
            }
            if ($method === 'notifications/initialized') {
                return Http::response('', 202);
            }
            if ($method === 'tools/call') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => [
                        'content' => [['type' => 'text', 'text' => 'pong']],
                        'isError' => false,
                    ],
                ]);
            }

            return Http::response('', 200);
        });

        $result = (new McpClient)->callTool($this->server(), 'ping', []);

        $this->assertFalse($result['isError']);
        $this->assertSame('pong', $result['content'][0]['text']);
    }

    public function test_throws_on_protocol_error(): void
    {
        Http::fake(function ($request) {
            return Http::response([
                'jsonrpc' => '2.0',
                'id' => $request->data()['id'] ?? null,
                'error' => ['code' => -32601, 'message' => 'method not found'],
            ]);
        });

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('method not found');

        (new McpClient)->fetchTools($this->server());
    }
}
