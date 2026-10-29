<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Mcp\McpCallerResolver;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Request;

/**
 * The caller of a built-in MCP tool is read once from the signed identity
 * headers. A conversation only counts when it belongs to the calling agent,
 * and a bare workspace only identifies the caller on the analytics chat's
 * servers.
 */
function mcrRequest(string $path, array $headers): Request
{
    $request = Request::create($path, 'POST');
    $request->headers->add($headers);

    return $request;
}

it('resolves the agent, its conversation, the chatter and the connector key, and treats empty headers as no caller', function (): void {
    $agent = Agent::factory()->create();
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);

    $caller = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => $agent->external_id,
        'X-Chatvia-Conversation-Id' => $conversation->external_id,
        'X-Chatvia-Chatter-Id' => 'chatter-uuid',
        'Authorization' => 'Bearer connector-key',
    ]));
    $empty = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => '',
        'Authorization' => 'Bearer ',
    ]));

    expect($caller->agentExternalId)->toBe($agent->external_id)
        ->and($caller->agent?->is($agent))->toBeTrue()
        ->and($caller->conversationExternalId)->toBe($conversation->external_id)
        ->and($caller->conversation?->is($conversation))->toBeTrue()
        ->and($caller->chatterExternalId)->toBe('chatter-uuid')
        ->and($caller->connectorApiKey)->toBe('connector-key')
        ->and($caller->workspace)->toBeNull()
        ->and($empty->agentExternalId)->toBeNull()
        ->and($empty->agent)->toBeNull()
        ->and($empty->conversationExternalId)->toBeNull()
        ->and($empty->connectorApiKey)->toBeNull();
});

it('keeps the raw ids but no models for unknown or foreign records', function (): void {
    $agent = Agent::factory()->create();
    $foreignConversation = Conversation::factory()->create();

    $caller = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => $agent->external_id,
        'X-Chatvia-Conversation-Id' => $foreignConversation->external_id,
    ]));
    $ghost = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', ['X-Chatvia-Agent-Id' => 'ghost']));

    expect($caller->conversationExternalId)->toBe($foreignConversation->external_id)
        ->and($caller->conversation)->toBeNull()
        ->and($ghost->agentExternalId)->toBe('ghost')
        ->and($ghost->agent)->toBeNull();
});

it('honours a bare workspace caller only on the analytics chat servers, never beside an agent id or for a person\'s id', function (): void {
    $agent = Agent::factory()->create();
    $workspace = $agent->workspace;
    $chatter = Chatter::factory()->create();
    $teammate = User::factory()->employee($workspace->owner)->create();
    $resolve = fn (string $path, array $headers) => app(McpCallerResolver::class)->resolve(mcrRequest($path, $headers));
    $bare = ['X-Chatvia-Workspace-Id' => $workspace->external_id];

    $agentCall = $resolve('/mcp/account-analytics', ['X-Chatvia-Agent-Id' => $agent->external_id, ...$bare]);
    $chartsAgentCall = $resolve('/mcp/charts', ['X-Chatvia-Agent-Id' => $agent->external_id, ...$bare]);
    $ghostCall = $resolve('/mcp/account-analytics', ['X-Chatvia-Agent-Id' => 'ghost', ...$bare]);

    expect($resolve('/mcp/account-analytics', $bare)->workspace?->is($workspace))->toBeTrue()
        ->and($resolve('/mcp/charts', $bare)->workspace?->is($workspace))->toBeTrue()
        ->and($resolve('/mcp/weather', $bare)->workspace)->toBeNull()
        ->and($resolve('/mcp/forms', $bare)->workspace)->toBeNull()
        ->and($agentCall->agent?->is($agent))->toBeTrue()
        ->and($agentCall->workspace)->toBeNull()
        ->and($chartsAgentCall->workspace)->toBeNull()
        ->and($ghostCall->agent)->toBeNull()
        ->and($ghostCall->workspace)->toBeNull()
        ->and($resolve('/mcp/account-analytics', ['X-Chatvia-Workspace-Id' => $chatter->external_id])->workspace)->toBeNull()
        ->and($resolve('/mcp/account-analytics', ['X-Chatvia-Workspace-Id' => $workspace->owner->external_id])->workspace)->toBeNull()
        ->and($resolve('/mcp/account-analytics', ['X-Chatvia-Workspace-Id' => $teammate->external_id])->workspace)->toBeNull();
});
