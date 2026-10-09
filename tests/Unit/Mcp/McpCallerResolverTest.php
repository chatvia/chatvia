<?php

use App\Identity\Workspace\Models\User;
use App\Mcp\McpCallerResolver;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Request;
use Support\Enums\User\UserType;

/**
 * The caller of a built-in MCP tool is read once from the signed identity
 * headers. A conversation only counts when it belongs to the calling agent,
 * and a bare workspace only identifies the caller on the analytics chat's
 * servers.
 */
function mcpRequest(string $path, array $headers): Request
{
    $request = Request::create($path, 'POST');
    $request->headers->add($headers);

    return $request;
}

it('resolves the agent, its conversation, the chatter and the connector key', function (): void {
    $agent = Agent::factory()->create();
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);

    $caller = app(McpCallerResolver::class)->resolve(mcpRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => $agent->external_id,
        'X-Chatvia-Conversation-Id' => $conversation->external_id,
        'X-Chatvia-Chatter-Id' => 'chatter-uuid',
        'Authorization' => 'Bearer connector-key',
    ]));

    expect($caller->agentExternalId)->toBe($agent->external_id)
        ->and($caller->agent?->is($agent))->toBeTrue()
        ->and($caller->conversationExternalId)->toBe($conversation->external_id)
        ->and($caller->conversation?->is($conversation))->toBeTrue()
        ->and($caller->chatterExternalId)->toBe('chatter-uuid')
        ->and($caller->connectorApiKey)->toBe('connector-key')
        ->and($caller->workspace)->toBeNull();
});

it('keeps the raw ids but no models for unknown or foreign records', function (): void {
    $agent = Agent::factory()->create();
    $foreignConversation = Conversation::factory()->create();

    $caller = app(McpCallerResolver::class)->resolve(mcpRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => $agent->external_id,
        'X-Chatvia-Conversation-Id' => $foreignConversation->external_id,
    ]));

    expect($caller->conversationExternalId)->toBe($foreignConversation->external_id)
        ->and($caller->conversation)->toBeNull();

    $ghost = app(McpCallerResolver::class)->resolve(mcpRequest('/mcp/forms', ['X-Chatvia-Agent-Id' => 'ghost']));

    expect($ghost->agentExternalId)->toBe('ghost')
        ->and($ghost->agent)->toBeNull();
});

it('treats missing and empty headers as no caller', function (): void {
    $caller = app(McpCallerResolver::class)->resolve(mcpRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => '',
        'Authorization' => 'Bearer ',
    ]));

    expect($caller->agentExternalId)->toBeNull()
        ->and($caller->agent)->toBeNull()
        ->and($caller->conversationExternalId)->toBeNull()
        ->and($caller->connectorApiKey)->toBeNull();
});

it('honours a workspace caller only on the analytics chat servers', function (string $path, bool $honoured): void {
    $workspace = User::factory()->create(['type' => UserType::Workspace]);

    $caller = app(McpCallerResolver::class)->resolve(mcpRequest($path, ['X-Chatvia-Workspace-Id' => $workspace->external_id]));

    expect($caller->workspace?->is($workspace) ?? false)->toBe($honoured);
})->with([
    'account analytics' => ['/mcp/account-analytics', true],
    'charts' => ['/mcp/charts', true],
    'weather' => ['/mcp/weather', false],
    'forms' => ['/mcp/forms', false],
]);

it('ignores a workspace id that names a chatter', function (): void {
    $chatter = User::factory()->create(['type' => UserType::Chatter]);

    $caller = app(McpCallerResolver::class)->resolve(mcpRequest('/mcp/account-analytics', ['X-Chatvia-Workspace-Id' => $chatter->external_id]));

    expect($caller->workspace)->toBeNull();
});
