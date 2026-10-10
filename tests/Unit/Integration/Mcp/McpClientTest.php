<?php

namespace Tests\Unit\Integration\Mcp;

use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpClientTest extends TestCase
{
    private function server(): AgentMcpServer
    {
        $server = new AgentMcpServer;
        $server->id = 99;
        $server->name = 'Test';
        $server->slug = 'test';
        $server->url = 'https://mcp.example.test/rpc';
        $server->transport = 'streamable_http';
        $server->auth_type = 'bearer';
        $server->auth_value = 'tok-abc';
        $server->headers = [];
        $server->timeout_ms = 5000;
        $server->is_enabled = true;

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

        $tools = app(McpClient::class)->fetchTools($this->server());

        $this->assertCount(1, $tools);
        $this->assertSame('foo', $tools[0]->name);
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

        $result = app(McpClient::class)->callTool($this->server(), 'ping', []);

        $this->assertFalse($result->isError);
        $this->assertSame('pong', $result->text);
    }

    public function test_call_tool_stamps_caller_identity_headers(): void
    {
        Http::fake(function ($request) {
            $body = $request->data();
            $method = $body['method'] ?? '';

            if ($method === 'initialize') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => ['protocolVersion' => '2025-06-18'],
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
                        'content' => [['type' => 'text', 'text' => 'ok']],
                        'isError' => false,
                    ],
                ]);
            }

            return Http::response('', 200);
        });

        app(McpClient::class)->callTool($this->server(), 'ping', [], [
            'X-Chatvia-Agent-Id' => 'agent-uuid',
            'X-Chatvia-User-Id' => 'user-uuid',
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return ($body['method'] ?? null) === 'tools/call'
                && $request->header('X-Chatvia-Agent-Id') === ['agent-uuid']
                && $request->header('X-Chatvia-User-Id') === ['user-uuid'];
        });
    }

    public function test_session_and_protocol_headers_are_not_duplicated_across_calls(): void
    {
        // Regression guard: Laravel's PendingRequest::withHeaders uses
        // array_merge_recursive, so reusing a request and chaining
        // withHeaders for each RPC would nest duplicate keys into arrays —
        // causing the wire to carry the same header twice. Notion (and other
        // strict MCP servers) interpret repeated `Mcp-Session-Id` headers as
        // comma-joined and 404 with "Session not found". Each RPC must
        // build a fresh PendingRequest.
        Http::fake(function ($request) {
            $body = $request->data();
            $method = $body['method'] ?? '';

            if ($method === 'initialize') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => ['protocolVersion' => '2025-06-18'],
                ], 200, ['Mcp-Session-Id' => 's-1']);
            }

            if ($method === 'tools/list') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => ['tools' => []],
                ]);
            }

            return Http::response('', 202);
        });

        app(McpClient::class)->fetchTools($this->server());

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            if (($body['method'] ?? null) !== 'tools/list') {
                return false;
            }

            // Guzzle exposes each header as an array of values; a single
            // value means the header was set once, which is what we want.
            // Two values means we re-introduced the accumulation bug.
            $sessionHeader = $request->header('Mcp-Session-Id');
            $protocolHeader = $request->header('MCP-Protocol-Version');

            $this->assertCount(1, $sessionHeader, 'Mcp-Session-Id was sent multiple times');
            $this->assertSame('s-1', $sessionHeader[0]);
            $this->assertCount(1, $protocolHeader, 'MCP-Protocol-Version was sent multiple times');
            $this->assertSame('2025-06-18', $protocolHeader[0]);

            return true;
        });
    }

    public function test_throws_on_protocol_error(): void
    {
        Http::fake(fn ($request) => Http::response([
            'jsonrpc' => '2.0',
            'id' => $request->data()['id'] ?? null,
            'error' => ['code' => -32601, 'message' => 'method not found'],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('method not found');

        app(McpClient::class)->fetchTools($this->server());
    }

    /**
     * Answers initialize / tools/call like a minimal MCP server.
     */
    private function fakeMcpServer(): void
    {
        Http::fake(function ($request) {
            $body = $request->data();
            $method = $body['method'] ?? '';

            if ($method === 'initialize') {
                return Http::response(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => ['protocolVersion' => '2025-06-18']]);
            }
            if ($method === 'tools/call') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => ['content' => [['type' => 'text', 'text' => 'ok']], 'isError' => false],
                ]);
            }

            return Http::response('', 202);
        });
    }

    private function internalServer(?string $connectorSlug): AgentMcpServer
    {
        $server = $this->server();
        $server->connector_slug = $connectorSlug;
        $server->url = rtrim((string) config('app.url'), '/').'/mcp/charts';
        $server->auth_type = 'none';
        $server->auth_value = null;

        return $server;
    }

    public function test_first_party_server_requests_carry_a_signature_over_body_and_identity(): void
    {
        $this->fakeMcpServer();

        app(McpClient::class)->callTool($this->internalServer('charts'), 'render-bar-chart', ['title' => 'x'], [
            'X-Chatvia-Agent-Id' => 'agent-uuid',
            'X-Chatvia-User-Id' => 'user-uuid',
        ]);

        $signed = 0;
        Http::assertSent(function (ClientRequest $request) use (&$signed): bool {
            $verified = InternalMcpSignature::verify(
                $request->header(InternalMcpSignature::TIMESTAMP_HEADER)[0] ?? null,
                $request->header(InternalMcpSignature::SIGNATURE_HEADER)[0] ?? null,
                $request->method(),
                (string) parse_url($request->url(), PHP_URL_PATH),
                $request->body(),
                InternalMcpSignature::identityHeadersFrom($request->headers()),
            );
            $signed += $verified ? 1 : 0;

            return $verified;
        });

        // initialize, notifications/initialized and tools/call are all signed.
        $this->assertSame(3, $signed);
    }

    public function test_first_party_server_is_sent_to_the_internal_url_with_a_valid_signature(): void
    {
        config(['services.builtin_mcp.internal_url' => 'http://app:8080']);
        $this->fakeMcpServer();

        app(McpClient::class)->callTool($this->internalServer('charts'), 'render-bar-chart', ['title' => 'x']);

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://app:8080/mcp/charts'
            && InternalMcpSignature::verify(
                $request->header(InternalMcpSignature::TIMESTAMP_HEADER)[0] ?? null,
                $request->header(InternalMcpSignature::SIGNATURE_HEADER)[0] ?? null,
                $request->method(),
                parse_url($request->url(), PHP_URL_PATH),
                $request->body(),
                InternalMcpSignature::identityHeadersFrom($request->headers()),
            ));
        Http::assertNotSent(fn (ClientRequest $request): bool => ! str_starts_with($request->url(), 'http://app:8080/'));
    }

    public function test_internal_url_never_redirects_a_third_party_server(): void
    {
        config(['services.builtin_mcp.internal_url' => 'http://app:8080']);
        $this->fakeMcpServer();

        app(McpClient::class)->callTool($this->server(), 'ping', []);

        Http::assertNotSent(fn (ClientRequest $request): bool => str_starts_with($request->url(), 'http://app:8080'));
    }

    public function test_custom_server_pointing_at_an_internal_url_is_not_signed(): void
    {
        $this->fakeMcpServer();

        // A workspace's own server that merely uses our /mcp/ URL has no
        // connector_slug, so it must not receive our proof-of-origin.
        app(McpClient::class)->callTool($this->internalServer(null), 'render-bar-chart', []);

        Http::assertNotSent(fn (ClientRequest $request): bool => $request->hasHeader(InternalMcpSignature::SIGNATURE_HEADER));
    }

    public function test_third_party_server_is_never_signed(): void
    {
        $this->fakeMcpServer();

        app(McpClient::class)->callTool($this->server(), 'ping', [], ['X-Chatvia-Agent-Id' => 'agent-uuid']);

        Http::assertNotSent(fn (ClientRequest $request): bool => $request->hasHeader(InternalMcpSignature::SIGNATURE_HEADER));
    }

    public function test_first_party_server_ignores_stored_custom_headers(): void
    {
        $this->fakeMcpServer();

        $server = $this->internalServer('charts');
        $server->headers = [['key' => 'X-Chatvia-Workspace-Id', 'value' => 'someone-else']];

        app(McpClient::class)->callTool($server, 'render-bar-chart', [], ['X-Chatvia-Agent-Id' => 'agent-uuid']);

        Http::assertNotSent(fn (ClientRequest $request): bool => $request->hasHeader('X-Chatvia-Workspace-Id'));
    }

    public function test_initialize_introduces_the_client_by_the_brand_name(): void
    {
        config(['chatvia.brand.name' => 'Acme Chat']);

        Http::fake(function ($request) {
            $body = $request->data();
            $method = $body['method'] ?? '';

            if ($method === 'initialize') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'],
                    'result' => ['protocolVersion' => '2025-03-26', 'capabilities' => (object) []],
                ], 200);
            }

            if ($method === 'tools/list') {
                return Http::response(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => ['tools' => []]], 200);
            }

            return Http::response('', 202);
        });

        app(McpClient::class)->fetchTools($this->server());

        Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'initialize'
            && $request->data()['params']['clientInfo']['name'] === 'acme-chat');
    }
}
