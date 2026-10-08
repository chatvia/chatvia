<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Services\InternalMcpSignature;

/**
 * The internal /mcp/* routes trust the X-Chatvia-* identity headers, so the
 * HMAC must cover them, the body, the method and the path. A signature taken
 * from one request must not authorise a different workspace, body or route.
 */
function internalToolCall(string $tool = 'account-overview'): array
{
    return ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) []]];
}

/**
 * @param  array<string, string>  $identity
 * @return array<string, string>
 */
function internalSignedHeaders(string $path, array $payload, array $identity): array
{
    return array_merge($identity, InternalMcpSignature::headers('POST', $path, (string) json_encode($payload), $identity));
}

beforeEach(function (): void {
    $this->workspace = User::factory()->create();
    $this->identity = ['X-Chatvia-Workspace-Id' => $this->workspace->external_id];
});

it('accepts a correctly signed analytics call in workspace mode', function (): void {
    $call = internalToolCall();

    $response = $this->withHeaders(internalSignedHeaders('/mcp/account-analytics', $call, $this->identity))
        ->postJson('/mcp/account-analytics', $call);

    expect($response->getStatusCode())->not->toBeIn([401, 403]);
});

it('rejects an unsigned call', function (): void {
    $this->withHeaders($this->identity)
        ->postJson('/mcp/account-analytics', internalToolCall())
        ->assertStatus(401);
});

it('rejects a signature replayed with another workspace id', function (): void {
    $call = internalToolCall();
    $headers = internalSignedHeaders('/mcp/account-analytics', $call, $this->identity);

    $victim = User::factory()->create();
    $headers['X-Chatvia-Workspace-Id'] = $victim->external_id;

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('rejects a signature when an extra identity header is added', function (): void {
    $call = internalToolCall();
    $headers = internalSignedHeaders('/mcp/account-analytics', $call, $this->identity);
    $headers['X-Chatvia-Agent-Id'] = 'someone-elses-agent';

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('rejects a signature replayed with a different body', function (): void {
    $headers = internalSignedHeaders('/mcp/account-analytics', internalToolCall('account-overview'), $this->identity);

    $this->withHeaders($headers)
        ->postJson('/mcp/account-analytics', internalToolCall('top-topics'))
        ->assertStatus(401);
});

it('rejects a signature replayed on a different route', function (): void {
    $call = internalToolCall();
    $headers = internalSignedHeaders('/mcp/charts', $call, $this->identity);

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('rejects an expired signature', function (): void {
    $call = internalToolCall();
    $headers = internalSignedHeaders('/mcp/account-analytics', $call, $this->identity);

    $this->travel(10)->minutes();

    $this->withHeaders($headers)->postJson('/mcp/account-analytics', $call)->assertStatus(401);
});

it('only accepts workspace mode on the analytics chat servers', function (): void {
    $call = internalToolCall('current-weather');

    // Validly signed, but /mcp/weather is a per-agent connector: a workspace
    // id alone must not unlock it.
    $this->withHeaders(internalSignedHeaders('/mcp/weather', $call, $this->identity))
        ->postJson('/mcp/weather', $call)
        ->assertStatus(403);
});
