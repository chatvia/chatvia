<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
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

it('resolves the agent, its conversation, the chatter and the connector key', function (): void {
    $agent = Agent::factory()->create();
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);

    $caller = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', [
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

    $caller = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => $agent->external_id,
        'X-Chatvia-Conversation-Id' => $foreignConversation->external_id,
    ]));

    expect($caller->conversationExternalId)->toBe($foreignConversation->external_id)
        ->and($caller->conversation)->toBeNull();

    $ghost = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', ['X-Chatvia-Agent-Id' => 'ghost']));

    expect($ghost->agentExternalId)->toBe('ghost')
        ->and($ghost->agent)->toBeNull();
});

it('treats missing and empty headers as no caller', function (): void {
    $caller = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/forms', [
        'X-Chatvia-Agent-Id' => '',
        'Authorization' => 'Bearer ',
    ]));

    expect($caller->agentExternalId)->toBeNull()
        ->and($caller->agent)->toBeNull()
        ->and($caller->conversationExternalId)->toBeNull()
        ->and($caller->connectorApiKey)->toBeNull();
});

it('honours a workspace caller only on the analytics chat servers', function (string $path, bool $honoured): void {
    $workspace = User::factory()->create()->workspace();

    $caller = app(McpCallerResolver::class)->resolve(mcrRequest($path, ['X-Chatvia-Workspace-Id' => $workspace->external_id]));

    expect($caller->workspace?->is($workspace) ?? false)->toBe($honoured);
})->with([
    'account analytics' => ['/mcp/account-analytics', true],
    'charts' => ['/mcp/charts', true],
    'weather' => ['/mcp/weather', false],
    'forms' => ['/mcp/forms', false],
]);

it('runs an agent\'s call on an analytics server for the agent, never in workspace mode', function (string $path): void {
    $agent = Agent::factory()->create();

    $caller = app(McpCallerResolver::class)->resolve(mcrRequest($path, [
        'X-Chatvia-Agent-Id' => $agent->external_id,
        'X-Chatvia-Workspace-Id' => $agent->workspace->external_id,
    ]));

    expect($caller->agent?->is($agent))->toBeTrue()
        ->and($caller->workspace)->toBeNull();
})->with([
    'account analytics' => ['/mcp/account-analytics'],
    'charts' => ['/mcp/charts'],
]);

it('ignores the workspace header when an unknown agent id comes with it', function (): void {
    $workspace = User::factory()->create()->workspace();

    $caller = app(McpCallerResolver::class)->resolve(mcrRequest('/mcp/account-analytics', [
        'X-Chatvia-Agent-Id' => 'ghost',
        'X-Chatvia-Workspace-Id' => $workspace->external_id,
    ]));

    expect($caller->agent)->toBeNull()
        ->and($caller->workspace)->toBeNull();
});

it('ignores a workspace id that names a chatter or a staff account', function (): void {
    $chatter = Chatter::factory()->create();
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();

    $resolve = fn (string $externalId): ?Workspace => app(McpCallerResolver::class)
        ->resolve(mcrRequest('/mcp/account-analytics', ['X-Chatvia-Workspace-Id' => $externalId]))
        ->workspace;

    expect($resolve($chatter->external_id))->toBeNull()
        ->and($resolve($owner->external_id))->toBeNull()
        ->and($resolve($teammate->external_id))->toBeNull()
        ->and($resolve($owner->workspace()->external_id)?->is($owner->workspace()))->toBeTrue();
});
