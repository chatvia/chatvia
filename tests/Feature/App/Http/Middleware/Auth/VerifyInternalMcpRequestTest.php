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
    'signed without a nonce' => fn (array $request): array => [...$request, 'headers' => vimrSignedByHand($request['path'], $request['body'], test()->identity, null)],
    'an expired signature' => function (array $request): array {
        test()->travel(10)->minutes();

        return $request;
    },
]);

it('runs each signed call once with its resolved caller, in workspace mode and for an agent that attached the built-in, and rejects a replayed copy', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $headers = InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity);
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->builtin('charts')->attachedTo($agent)->create();
    $chart = vimrBarChartCall();

    $response = $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertOk();

    expect($response->json('result.isError'))->toBeFalse()
        ->and($response->json('result.structuredContent.total_messages'))->toBe(0)
        ->and(app(McpCallerContext::class)->workspace?->is($this->workspace))->toBeTrue();

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
    $this->withHeaders(InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call)->assertOk();
    $this->withHeaders(vimrSignedByHand('/mcp/account-analytics', $call, $this->identity, Str::repeat('b', 40)))
        ->postJson('/mcp/account-analytics', $call)->assertOk();
    $this->flushHeaders()->withHeaders(InternalMcp::signedHeaders('/mcp/charts', $chart, ['X-Chatvia-Agent-Id' => $agent->external_id]))
        ->postJson('/mcp/charts', $chart)
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});

it('refuses a signed tool call where the caller has not turned this built-in on, but still lists the tools', function (Closure $caller): void {
    $agent = Agent::factory()->create();
    [$path, $call, $identity] = $caller($agent);
    $listing = ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/list', 'params' => (object) []];

    $this->withHeaders(InternalMcp::signedHeaders($path, $call, $identity))
        ->postJson($path, $call)
        ->assertStatus(403);
    $tools = $this->flushHeaders()->withHeaders(InternalMcp::signedHeaders($path, $listing, $identity))
        ->postJson($path, $listing)
        ->assertOk()
        ->json('result.tools');

    expect($tools)->not->toBeEmpty();
})->with([
    'a workspace id on a per-agent built-in' => fn (Agent $agent): array => ['/mcp/weather', InternalMcp::toolCall('current-weather'), test()->identity],
    'nothing attached' => fn (Agent $agent): array => ['/mcp/charts', vimrBarChartCall(), ['X-Chatvia-Agent-Id' => $agent->external_id]],
    'attached but switched off' => function (Agent $agent): array {
        AgentMcpServer::factory()->builtin('charts')->disabled()->attachedTo($agent)->create();

        return ['/mcp/charts', vimrBarChartCall(), ['X-Chatvia-Agent-Id' => $agent->external_id]];
    },
    'another built-in attached' => function (Agent $agent): array {
        AgentMcpServer::factory()->builtin('forms')->attachedTo($agent)->create();

        return ['/mcp/charts', vimrBarChartCall(), ['X-Chatvia-Agent-Id' => $agent->external_id]];
    },
    'a custom server at the same address' => function (Agent $agent): array {
        AgentMcpServer::factory()->attachedTo($agent)->create(['url' => Str::rtrim((string) config('app.url'), '/').'/mcp/charts']);

        return ['/mcp/charts', vimrBarChartCall(), ['X-Chatvia-Agent-Id' => $agent->external_id]];
    },
]);
