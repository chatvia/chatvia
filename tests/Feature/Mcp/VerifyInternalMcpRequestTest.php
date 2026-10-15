<?php

use App\Identity\Workspace\Models\User;
use App\Mcp\McpCallerContext;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Tests\Support\Mcp\InternalMcp;

/**
 * The internal /mcp/* routes trust the X-Chatvia-* identity headers, so the
 * HMAC must cover them, the body, the method and the path. A signature taken
 * from one request must not authorise a different workspace, body or route.
 */
beforeEach(function (): void {
    $this->workspace = User::factory()->create()->workspace();
    $this->identity = ['X-Chatvia-Workspace-Id' => $this->workspace->external_id];
});

it('rejects an unsigned call', function (): void {
    $this->withHeaders($this->identity)
        ->postJson('/mcp/account-analytics', InternalMcp::toolCall('get-account-overview-tool'))
        ->assertStatus(401);
});

it('rejects a signature replayed with another workspace id', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $headers = InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity);

    $victim = User::factory()->create()->workspace();
    $headers['X-Chatvia-Workspace-Id'] = $victim->external_id;

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('rejects a signature when an extra identity header is added', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $headers = InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity);
    $headers['X-Chatvia-Agent-Id'] = 'someone-elses-agent';

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('rejects a signature replayed with a different body', function (): void {
    $headers = InternalMcp::signedHeaders('/mcp/account-analytics', InternalMcp::toolCall('get-account-overview-tool'), $this->identity);

    $this->withHeaders($headers)
        ->postJson('/mcp/account-analytics', InternalMcp::toolCall('get-top-topics-tool'))
        ->assertStatus(401);
});

it('rejects a signature replayed on a different route', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $headers = InternalMcp::signedHeaders('/mcp/charts', $call, $this->identity);

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('rejects an expired signature', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $headers = InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity);

    $this->travel(10)->minutes();

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
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
    $workspace = User::factory()->create()->workspace();
    $identity = ['X-Chatvia-Workspace-Id' => $workspace->external_id];
    $call = InternalMcp::toolCall('get-account-overview-tool');

    $response = $this->withHeaders(InternalMcp::signedHeaders('/mcp/account-analytics', $call, $identity))
        ->postJson('/mcp/account-analytics', $call)
        ->assertOk();

    // The tool only answers when it received the workspace from the middleware.
    expect($response->json('result.isError'))->toBeFalse()
        ->and($response->json('result.structuredContent.total_messages'))->toBe(0)
        ->and(app(McpCallerContext::class)->workspace?->is($workspace))->toBeTrue();
});

it('resolves no caller for a call that runs no tool', function (): void {
    $call = ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/list', 'params' => (object) []];

    $this->withHeaders(InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call)
        ->assertOk();

    expect(app(McpCallerContext::class)->workspace)->toBeNull();
});

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

it('rejects a request whose nonce was removed or swapped', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');
    $headers = InternalMcp::signedHeaders('/mcp/account-analytics', $call, $this->identity);

    $swapped = [...$headers, InternalMcpSignature::NONCE_HEADER => str_repeat('a', 40)];
    $this->withHeaders($swapped)->postJson('/mcp/account-analytics', $call)->assertStatus(401);

    unset($headers[InternalMcpSignature::NONCE_HEADER]);
    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

/**
 * Headers signed without a nonce: none in the request and none in the
 * signed identity lines.
 *
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $identity
 * @return array<string, string>
 */
function internalHeadersWithoutNonce(string $path, array $payload, array $identity): array
{
    $timestamp = (string) now()->timestamp;
    $lines = collect($identity)
        ->mapWithKeys(fn (string $value, string $name): array => [strtolower($name) => $value])
        ->sortKeys()
        ->map(fn (string $value, string $name): string => $name.':'.trim($value))
        ->implode("\n");
    $canonical = implode("\n", [$timestamp, 'POST', $path, hash('sha256', (string) json_encode($payload)), $lines]);

    return [
        ...$identity,
        InternalMcpSignature::TIMESTAMP_HEADER => $timestamp,
        InternalMcpSignature::SIGNATURE_HEADER => hash_hmac('sha256', $canonical, InternalMcpSignature::secret()),
    ];
}

it('refuses a request signed without a nonce', function (): void {
    $call = InternalMcp::toolCall('get-account-overview-tool');

    $this->withHeaders(internalHeadersWithoutNonce('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call)
        ->assertStatus(401);
});
