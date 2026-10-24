<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tests\Support\Mcp\InternalMcp;

/**
 * The internal /mcp/* routes trust the X-Chatvia-* identity headers, so the
 * HMAC must cover them, the body, the method and the path. A signature taken
 * from one request must not authorise a different workspace, body or route.
 * A signed tool call then runs only for an agent that turned this built-in
 * on, or for the owner's analytics chat on the servers it uses.
 */
beforeEach(function (): void {
    $this->workspace = User::factory()->create()->workspace();
    $this->identity = ['X-Chatvia-Workspace-Id' => $this->workspace->external_id];
});

/**
 * Headers signed by hand in the signature's canonical form, with the nonce
 * as one more signed identity line, or with no nonce at all when null.
 *
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $identity
 * @return array<string, string>
 */
function vimrSignedByHand(string $path, array $payload, array $identity, ?string $nonce): array
{
    if ($nonce !== null) {
        $identity[InternalMcpSignature::NONCE_HEADER] = $nonce;
    }

    $timestamp = (string) now()->timestamp;
    $lines = collect($identity)
        ->mapWithKeys(fn (string $value, string $name): array => [Str::lower($name) => $value])
        ->sortKeys()
        ->map(fn (string $value, string $name): string => $name.':'.Str::trim($value))
        ->implode("\n");
    $canonical = implode("\n", [$timestamp, 'POST', $path, hash('sha256', (string) json_encode($payload)), $lines]);

    return [
        ...$identity,
        InternalMcpSignature::TIMESTAMP_HEADER => $timestamp,
        InternalMcpSignature::SIGNATURE_HEADER => hash_hmac('sha256', $canonical, InternalMcpSignature::secret()),
    ];
}

/** A bar chart call the charts tool accepts. */
function vimrBarChartCall(): array
{
    return InternalMcp::toolCall('render-bar-chart-tool', [
        'categories' => ['Mon', 'Tue'],
        'series' => [['name' => 'Visitors', 'data' => [10, 20]]],
        'summary' => 'Visitors per day.',
    ]);
}

it('rejects a request its signature does not cover', function (Closure $change): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $request = [
        'path' => '/mcp/account-analytics',
        'headers' => InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity),
        'body' => $call,
    ];

    ['path' => $path, 'headers' => $headers, 'body' => $body] = $change($request);

    $this->withHeaders($headers)->postJson($path, $body)->assertStatus(401);
})->with([
    'no signature' => fn (array $request): array => [...$request, 'headers' => Arr::only($request['headers'], 'X-Chatvia-Workspace-Id')],
    'another workspace id' => fn (array $request): array => [...$request, 'headers' => [...$request['headers'], 'X-Chatvia-Workspace-Id' => User::factory()->create()->workspace()->external_id]],
    'an identity header added' => fn (array $request): array => [...$request, 'headers' => [...$request['headers'], 'X-Chatvia-Agent-Id' => 'someone-elses-agent']],
    'a different body' => fn (array $request): array => [...$request, 'body' => InternalMcp::toolCall('get-top-topics-tool')],
    'a different route' => fn (array $request): array => [...$request, 'path' => '/mcp/charts'],
    'the nonce swapped' => fn (array $request): array => [...$request, 'headers' => [...$request['headers'], InternalMcpSignature::NONCE_HEADER => Str::repeat('a', 40)]],
    'the nonce removed' => fn (array $request): array => [...$request, 'headers' => Arr::except($request['headers'], InternalMcpSignature::NONCE_HEADER)],
    'an expired signature' => function (array $request): array {
        test()->travel(10)->minutes();

        return $request;
    },
]);

it('rejects an identical copy of a request it already accepted', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $headers = InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity);

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertOk();
    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('signs each request with its own nonce, so two identical calls both pass', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');

    $this->withHeaders(InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call)->assertOk();
    $this->withHeaders(InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call)->assertOk();
});

it('accepts a request signed by hand with a nonce, so the hand-built form matches the real one', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');

    $this->withHeaders(vimrSignedByHand('/mcp/account-analytics', $call, $this->identity, Str::repeat('b', 40)))
        ->postJson('/mcp/account-analytics', $call)
        ->assertOk();
});

it('refuses a request signed without a nonce', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');

    $this->withHeaders(vimrSignedByHand('/mcp/account-analytics', $call, $this->identity, null))
        ->postJson('/mcp/account-analytics', $call)
        ->assertStatus(401);
});

it('only accepts workspace mode on the analytics chat servers', function (): void {
    $call = InternalMcp::toolCall('current-weather');

    // Validly signed, but /mcp/weather is a per-agent connector: a workspace
    // id alone must not unlock it.
    $this->withHeaders(InternalMcp::signedHeaders('/mcp/weather', $call, $this->identity))
        ->postJson('/mcp/weather', $call)
        ->assertStatus(403);
});

it('hands an authorised tools/call its resolved caller', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');

    $response = $this->withHeaders(InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call)
        ->assertOk();

    // The tool only answers when it received the workspace from the middleware.
    expect($response->json('result.isError'))->toBeFalse()
        ->and($response->json('result.structuredContent.total_messages'))->toBe(0)
        ->and(app(McpCallerContext::class)->workspace?->is($this->workspace))->toBeTrue();
});

it('lets the analytics chat draw charts in workspace mode', function (): void {
    $chart = vimrBarChartCall();

    $this->withHeaders(InternalMcp::signedHeaders('/mcp/charts', $chart, $this->identity))
        ->postJson('/mcp/charts', $chart)
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});

it('resolves no caller for a call that runs no tool', function (): void {
    $call = ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/list', 'params' => (object) []];

    $this->withHeaders(InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call)
        ->assertOk();

    expect(app(McpCallerContext::class)->workspace)->toBeNull();
});

it('refuses a tool call for an agent that has not turned this built-in on', function (Closure $attach): void {
    $agent = Agent::factory()->create();
    $attach($agent);
    $chart = vimrBarChartCall();

    $this->withHeaders(InternalMcp::signedHeaders('/mcp/charts', $chart, ['X-Chatvia-Agent-Id' => $agent->external_id]))
        ->postJson('/mcp/charts', $chart)
        ->assertStatus(403);
})->with([
    'nothing attached' => fn (Agent $agent): null => null,
    'attached but switched off' => fn (Agent $agent): AgentMcpServer => AgentMcpServer::factory()->builtin('charts')->disabled()->attachedTo($agent)->create(),
    'another built-in attached' => fn (Agent $agent): AgentMcpServer => AgentMcpServer::factory()->builtin('forms')->attachedTo($agent)->create(),
    'a custom server at the same address' => fn (Agent $agent): AgentMcpServer => AgentMcpServer::factory()->attachedTo($agent)->create(['url' => Str::rtrim((string) config('app.url'), '/').'/mcp/charts']),
]);

it('runs a tool for an agent that attached this built-in', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->builtin('charts')->attachedTo($agent)->create();
    $chart = vimrBarChartCall();

    $this->withHeaders(InternalMcp::signedHeaders('/mcp/charts', $chart, ['X-Chatvia-Agent-Id' => $agent->external_id]))
        ->postJson('/mcp/charts', $chart)
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});
