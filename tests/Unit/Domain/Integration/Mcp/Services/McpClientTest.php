<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Contracts\AccessTokenRefresher;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Domain\Integration\Mcp\Services\McpClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Support\Http\SafeUrl;

/**
 * The MCP client speaks JSON-RPC over streamable HTTP: it lists and calls
 * tools with the session and caller headers, reads JSON or server-sent event
 * replies (Linear and Atlassian answer with one `text/event-stream` frame)
 * and reports a server's errors. Every RPC builds a fresh request, as a
 * reused one would carry `Mcp-Session-Id` twice and strict servers such as
 * Notion then answer 404. A first-party server (one of our builtins) is
 * reached at the internal URL with a signed request and without stored
 * custom headers; a third-party or custom server never is, is never signed,
 * and is refused at a private address. Chart blocks come only from our own
 * charts server. A failed OAuth token refresh still sends the call, so the
 * server's own 401 is what the workspace sees.
 */

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

/** Answers `initialize` and a `tools/call` whose result carries an ECharts block. */
function mclFakeChartServer(): void
{
    Http::fake(function (ClientRequest $request): PromiseInterface {
        $body = $request->data();

        return match ($body['method'] ?? '') {
            'initialize' => Http::response(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => []]),
            'tools/call' => Http::response(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => [
                'content' => [['type' => 'text', 'text' => 'chart']],
                'structuredContent' => ['echarts_option' => ['tooltip' => ['formatter' => '<img src=x onerror=alert(1)>']]],
            ]]),
            default => Http::response('', 202),
        };
    });
}

/** Whether a recorded request carries a valid internal signature. */
function mclSigned(ClientRequest $request): bool
{
    return InternalMcpSignature::verify(
        $request->header(InternalMcpSignature::TIMESTAMP_HEADER)[0] ?? null,
        $request->header(InternalMcpSignature::SIGNATURE_HEADER)[0] ?? null,
        $request->method(),
        Uri::of($request->url())->getUri()->getPath(),
        $request->body(),
        InternalMcpSignature::identityHeadersFrom($request->headers()),
    );
}

it('lists and calls tools over JSON-RPC with the session, protocol and caller headers sent once', function (): void {
    mclFakeServer([['name' => 'foo', 'description' => 'fixture', 'inputSchema' => ['type' => 'object']]], sessionId: 's-1');
    $client = app(McpClient::class);

    $tools = $client->fetchTools(mclServer());
    $result = $client->callTool(mclServer(), 'ping', [], ['X-Chatvia-Agent-Id' => 'agent-uuid', 'X-Chatvia-User-Id' => 'user-uuid']);
    $client->callTool(mclServer(), 'ping', ['limit' => 5]);

    $toolsList = Http::recorded(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'tools/list');
    [$listRequest] = $toolsList->first();
    expect($tools)->toHaveCount(1)
        ->and($tools[0]->name)->toBe('foo')
        ->and($result->isError)->toBeFalse()
        ->and($result->text)->toBe('pong')
        ->and($toolsList)->toHaveCount(1)
        ->and($listRequest->header('Mcp-Session-Id'))->toBe(['s-1'])
        ->and($listRequest->header('MCP-Protocol-Version'))->toBe(['2025-06-18']);
    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'tools/call'
        && Str::contains($request->body(), '"arguments":{}')
        && $request->header('X-Chatvia-Agent-Id') === ['agent-uuid']
        && $request->header('X-Chatvia-User-Id') === ['user-uuid']);
    Http::assertSent(fn (ClientRequest $request): bool => Str::contains($request->body(), '"arguments":{"limit":5}'));
});

it('reads a reply sent as a server-sent event', function (): void {
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

it('still sends the call with the stored token when the OAuth token refresh fails', function (): void {
    app()->instance(AccessTokenRefresher::class, new class implements AccessTokenRefresher
    {
        public function refreshIfStale(AgentMcpServer $server): void
        {
            throw new RuntimeException('refresh denied');
        }
    });
    mclFakeServer();
    $server = mclServer();
    $server->oauth_token_endpoint = 'https://auth.example.test/token';
    $server->oauth_expires_at = now()->subMinute();

    $result = app(McpClient::class)->callTool($server, 'ping', []);

    expect($result->text)->toBe('pong');
    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['method'] ?? null) === 'tools/call'
        && $request->header('Authorization') === ['Bearer tok-abc']);
});

it('throws on a server error, a reply to another request, or an HTTP failure', function (Closure $reply, string $message, ?int $code): void {
    Http::fake($reply);

    expect(fn (): array => app(McpClient::class)->fetchTools(mclServer()))
        ->toThrow(function (McpException $e) use ($message, $code): void {
            $code === null
                ? expect($e->getMessage())->toContain($message)
                : expect($e->getMessage())->toBe($message)->and($e->getCode())->toBe($code);
        });
})->with([
    'a JSON-RPC error' => [fn (ClientRequest $request): PromiseInterface => Http::response([
        'jsonrpc' => '2.0',
        'id' => $request->data()['id'] ?? null,
        'error' => ['code' => -32601, 'message' => 'method not found'],
    ]), 'method not found', null],
    'an id that is not the one sent' => [fn (): PromiseInterface => Http::response([
        'jsonrpc' => '2.0',
        'id' => 'someone-elses-id',
        'result' => ['protocolVersion' => '2025-06-18'],
    ]), 'MCP response id mismatch for initialize', null],
    'an HTTP error, by status and the start of the body' => [fn (): PromiseInterface => Http::response('Service Unavailable '.Str::repeat('x', 300), 503), 'MCP server returned HTTP 503: Service Unavailable '.Str::repeat('x', 180), 503],
]);

it('signs every request to a first-party server and sends it to the internal URL, but never a server Chatvia does not run', function (): void {
    config(['services.builtin_mcp.internal_url' => 'http://app:8080']);
    mclFakeServer();
    $firstParty = mclInternalServer('charts');
    $firstParty->headers = [['key' => 'X-Chatvia-Workspace-Id', 'value' => 'someone-else']];

    app(McpClient::class)->callTool($firstParty, 'render-bar-chart', ['title' => 'x'], ['X-Chatvia-Agent-Id' => 'agent-uuid', 'X-Chatvia-User-Id' => 'user-uuid']);
    app(McpClient::class)->callTool(mclInternalServer(null), 'render-bar-chart', []);
    app(McpClient::class)->callTool(mclServer(), 'ping', [], ['X-Chatvia-Agent-Id' => 'agent-uuid']);

    $internal = Http::recorded(fn (ClientRequest $request): bool => Str::startsWith($request->url(), 'http://app:8080/'));
    expect($internal)->toHaveCount(3)
        ->and($internal->every(fn (array $pair): bool => $pair[0]->url() === 'http://app:8080/mcp/charts' && mclSigned($pair[0])))->toBeTrue();
    Http::assertNotSent(fn (ClientRequest $request): bool => ! Str::startsWith($request->url(), 'http://app:8080/')
        && $request->hasHeader(InternalMcpSignature::SIGNATURE_HEADER));
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->hasHeader('X-Chatvia-Workspace-Id'));
});

it('refuses a custom server at a private address, even at our own /mcp/ URL, but reaches a first-party one there', function (): void {
    app()['env'] = 'production';
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['127.0.0.1']);
    mclFakeServer();

    try {
        expect(fn () => app(McpClient::class)->callTool(mclInternalServer(null), 'render-bar-chart', []))
            ->toThrow(McpException::class, 'private or reserved address');
        Http::assertNothingSent();

        app(McpClient::class)->callTool(mclInternalServer('charts'), 'render-bar-chart', ['title' => 'x']);
    } finally {
        SafeUrl::resolveHostsUsing(null);
    }

    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['method'] ?? '') === 'tools/call');
});

it('renders chart blocks only from our own charts server', function (Closure $server, int $blocks): void {
    mclFakeChartServer();

    $result = app(McpClient::class)->callTool($server(), 'render-bar-chart', []);

    expect($result->artifacts())->toHaveCount($blocks);
})->with([
    'the builtin charts server' => [fn (): AgentMcpServer => mclInternalServer('charts'), 1],
    'a custom server at the same URL' => [fn (): AgentMcpServer => mclInternalServer(null), 0],
    'a third-party server' => [fn (): AgentMcpServer => mclServer(), 0],
]);
