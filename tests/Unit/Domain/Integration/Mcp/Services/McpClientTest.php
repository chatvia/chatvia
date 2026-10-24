<?php

use Domain\Integration\Mcp\Contracts\AccessTokenRefresher;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Domain\Integration\Mcp\Services\McpClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Mockery\MockInterface;

/** A third-party server reached with a bearer token (not saved). */
function mclServer(): AgentMcpServer
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

/**
 * A server at Chatvia's own /mcp/charts URL: one of our builtins when it has
 * the connector slug, a workspace's custom server when it has none.
 */
function mclInternalServer(?string $connectorSlug): AgentMcpServer
{
    $server = mclServer();
    $server->connector_slug = $connectorSlug;
    $server->url = Str::rtrim((string) config('app.url'), '/').'/mcp/charts';
    $server->auth_type = 'none';
    $server->auth_value = null;

    return $server;
}

/**
 * Answers like a minimal MCP server: `initialize` (issuing $sessionId when
 * given), `tools/list` with $tools, `tools/call` with a "pong" text result,
 * and an empty 202 for notifications and the session DELETE.
 *
 * @param  list<array<string, mixed>>  $tools
 */
function mclFakeServer(array $tools = [], ?string $sessionId = null): void
{
    Http::fake(function (ClientRequest $request) use ($tools, $sessionId): PromiseInterface {
        $body = $request->data();

        return match ($body['method'] ?? '') {
            'initialize' => Http::response(
                ['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => ['protocolVersion' => '2025-06-18']],
                200,
                $sessionId === null ? [] : ['Mcp-Session-Id' => $sessionId],
            ),
            'tools/list' => Http::response(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => ['tools' => $tools]]),
            'tools/call' => Http::response([
                'jsonrpc' => '2.0',
                'id' => $body['id'],
                'result' => ['content' => [['type' => 'text', 'text' => 'pong']], 'isError' => false],
            ]),
            default => Http::response('', 202),
        };
    });
}

it('returns the server catalog from tools/list', function (): void {
    mclFakeServer([['name' => 'foo', 'description' => 'fixture', 'inputSchema' => ['type' => 'object']]], sessionId: 's-1');

    $tools = app(McpClient::class)->fetchTools(mclServer());

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->name)->toBe('foo');
});

it('returns the tool result envelope of tools/call', function (): void {
    mclFakeServer();

    $result = app(McpClient::class)->callTool(mclServer(), 'ping', []);

    expect($result->isError)->toBeFalse()
        ->and($result->text)->toBe('pong');
});

it('stamps the caller identity headers on tools/call', function (): void {
    mclFakeServer();

    app(McpClient::class)->callTool(mclServer(), 'ping', [], [
        'X-Chatvia-Agent-Id' => 'agent-uuid',
        'X-Chatvia-User-Id' => 'user-uuid',
    ]);

    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'tools/call'
        && $request->header('X-Chatvia-Agent-Id') === ['agent-uuid']
        && $request->header('X-Chatvia-User-Id') === ['user-uuid']);
});

it('sends the session and protocol headers once on each call', function (): void {
    // Laravel's withHeaders merges with array_merge_recursive, so a reused
    // PendingRequest would carry a header twice. Strict servers such as
    // Notion read two `Mcp-Session-Id` headers as one comma-joined value and
    // answer 404 "Session not found", so every RPC builds a fresh request.
    mclFakeServer(sessionId: 's-1');

    app(McpClient::class)->fetchTools(mclServer());

    $toolsList = Http::recorded(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'tools/list');

    expect($toolsList)->toHaveCount(1);

    // Guzzle exposes each header as a list of values: one value means the
    // header was set once.
    [$request] = $toolsList->first();

    expect($request->header('Mcp-Session-Id'))->toBe(['s-1'])
        ->and($request->header('MCP-Protocol-Version'))->toBe(['2025-06-18']);
});

it('throws the JSON-RPC error a server returns', function (): void {
    Http::fake(fn (ClientRequest $request): PromiseInterface => Http::response([
        'jsonrpc' => '2.0',
        'id' => $request->data()['id'] ?? null,
        'error' => ['code' => -32601, 'message' => 'method not found'],
    ]));

    expect(fn (): array => app(McpClient::class)->fetchTools(mclServer()))
        ->toThrow(McpException::class, 'method not found');
});

it('reads a reply sent as a server-sent event', function (): void {
    // Linear and Atlassian answer with one `text/event-stream` frame whose
    // `data:` line carries the JSON-RPC response.
    Http::fake(function (ClientRequest $request): PromiseInterface {
        $body = $request->data();
        $result = match ($body['method'] ?? '') {
            'initialize' => ['protocolVersion' => '2025-06-18'],
            'tools/list' => ['tools' => [['name' => 'list_issues', 'description' => 'Lists issues']]],
            default => null,
        };

        if ($result === null) {
            return Http::response('', 202);
        }

        $frame = "event: message\r\ndata: ".json_encode(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => $result])."\r\n\r\n";

        return Http::response($frame, 200, ['Content-Type' => 'text/event-stream']);
    });

    $tools = app(McpClient::class)->fetchTools(mclServer());

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->name)->toBe('list_issues');
});

it('refuses a reply whose id is not the id it sent', function (): void {
    Http::fake(fn (ClientRequest $request): PromiseInterface => Http::response([
        'jsonrpc' => '2.0',
        'id' => 'someone-elses-id',
        'result' => ['protocolVersion' => '2025-06-18'],
    ]));

    expect(fn (): array => app(McpClient::class)->fetchTools(mclServer()))
        ->toThrow(McpException::class, 'MCP response id mismatch for initialize');
});

it('reports an HTTP error by status and the start of the body', function (): void {
    $body = 'Service Unavailable '.Str::repeat('x', 300);
    Http::fake(['mcp.example.test/*' => Http::response($body, 503)]);

    expect(fn (): array => app(McpClient::class)->fetchTools(mclServer()))
        ->toThrow(function (McpException $e) use ($body): void {
            expect($e->getMessage())->toBe('MCP server returned HTTP 503: '.Str::substr($body, 0, 200))
                ->and($e->getCode())->toBe(503);
        });
});

it('still sends the call when the OAuth token refresh fails', function (): void {
    // The server's own 401 is a better error for the workspace than our guess.
    $this->mock(AccessTokenRefresher::class, fn (MockInterface $mock) => $mock->shouldReceive('refreshIfStale')
        ->once()
        ->andThrow(new RuntimeException('refresh denied')));
    Log::spy();
    mclFakeServer();

    $server = mclServer();
    $server->oauth_token_endpoint = 'https://auth.example.test/token';
    $server->oauth_expires_at = now()->subMinute();

    $result = app(McpClient::class)->callTool($server, 'ping', []);

    expect($result->text)->toBe('pong');
    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'tools/call'
        && $request->header('Authorization') === ['Bearer tok-abc']);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'MCP token refresh failed'
        && $context['error'] === 'refresh denied');
});

it('signs every request to a first-party server over the body and the identity', function (): void {
    mclFakeServer();

    app(McpClient::class)->callTool(mclInternalServer('charts'), 'render-bar-chart', ['title' => 'x'], [
        'X-Chatvia-Agent-Id' => 'agent-uuid',
        'X-Chatvia-User-Id' => 'user-uuid',
    ]);

    $signed = 0;
    Http::assertSent(function (ClientRequest $request) use (&$signed): bool {
        $verified = InternalMcpSignature::verify(
            $request->header(InternalMcpSignature::TIMESTAMP_HEADER)[0] ?? null,
            $request->header(InternalMcpSignature::SIGNATURE_HEADER)[0] ?? null,
            $request->method(),
            Uri::of($request->url())->getUri()->getPath(),
            $request->body(),
            InternalMcpSignature::identityHeadersFrom($request->headers()),
        );
        $signed += $verified ? 1 : 0;

        return $verified;
    });

    // initialize, notifications/initialized and tools/call are all signed.
    expect($signed)->toBe(3);
});

it('sends a first-party server to the internal URL with a valid signature', function (): void {
    config(['services.builtin_mcp.internal_url' => 'http://app:8080']);
    mclFakeServer();

    app(McpClient::class)->callTool(mclInternalServer('charts'), 'render-bar-chart', ['title' => 'x']);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://app:8080/mcp/charts'
        && InternalMcpSignature::verify(
            $request->header(InternalMcpSignature::TIMESTAMP_HEADER)[0] ?? null,
            $request->header(InternalMcpSignature::SIGNATURE_HEADER)[0] ?? null,
            $request->method(),
            Uri::of($request->url())->getUri()->getPath(),
            $request->body(),
            InternalMcpSignature::identityHeadersFrom($request->headers()),
        ));
    Http::assertNotSent(fn (ClientRequest $request): bool => ! Str::startsWith($request->url(), 'http://app:8080/'));
});

it('never sends a third-party server to the internal URL', function (): void {
    config(['services.builtin_mcp.internal_url' => 'http://app:8080']);
    mclFakeServer();

    app(McpClient::class)->callTool(mclServer(), 'ping', []);

    Http::assertNotSent(fn (ClientRequest $request): bool => Str::startsWith($request->url(), 'http://app:8080'));
});

it('does not sign a custom server that points at an internal URL', function (): void {
    mclFakeServer();

    // A workspace's own server that merely uses our /mcp/ URL has no
    // connector_slug, so it must not receive our proof-of-origin.
    app(McpClient::class)->callTool(mclInternalServer(null), 'render-bar-chart', []);

    Http::assertNotSent(fn (ClientRequest $request): bool => $request->hasHeader(InternalMcpSignature::SIGNATURE_HEADER));
});

it('never signs a third-party server', function (): void {
    mclFakeServer();

    app(McpClient::class)->callTool(mclServer(), 'ping', [], ['X-Chatvia-Agent-Id' => 'agent-uuid']);

    Http::assertNotSent(fn (ClientRequest $request): bool => $request->hasHeader(InternalMcpSignature::SIGNATURE_HEADER));
});

it('ignores stored custom headers on a first-party server', function (): void {
    mclFakeServer();

    $server = mclInternalServer('charts');
    $server->headers = [['key' => 'X-Chatvia-Workspace-Id', 'value' => 'someone-else']];

    app(McpClient::class)->callTool($server, 'render-bar-chart', [], ['X-Chatvia-Agent-Id' => 'agent-uuid']);

    Http::assertNotSent(fn (ClientRequest $request): bool => $request->hasHeader('X-Chatvia-Workspace-Id'));
});

it('introduces the client as chatvia on initialize', function (): void {
    mclFakeServer();

    app(McpClient::class)->fetchTools(mclServer());

    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'initialize'
        && $request->data()['params']['clientInfo']['name'] === 'chatvia');
});
