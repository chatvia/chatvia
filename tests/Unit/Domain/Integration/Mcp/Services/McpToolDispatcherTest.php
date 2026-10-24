<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Domain\Integration\Tool\Data\ToolCallData;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tool arguments are visitor data and transport errors can carry URLs or
 * tokens: the dispatcher must log argument keys only and must never hand raw
 * transport errors to the model. A call it refuses never reaches the server.
 */
beforeEach(function (): void {
    Event::fake([McpToolInvoked::class]);

    $this->agent = Agent::factory()->create();
    $this->server = AgentMcpServer::factory()->attachedTo($this->agent)->create([
        'name' => 'CRM',
        'slug' => 'crm',
        'url' => 'https://crm.example.test/mcp',
    ]);

    $this->client = Mockery::mock(McpClient::class);
    app()->instance(McpClient::class, $this->client);

    $this->args = json_encode(['email' => 'jane@example.com', 'phone' => '+4712345678']);
});

it('returns the tool text and records the call with the catalog description and no error', function (): void {
    McpTool::factory()->for($this->server, 'mcpServer')->allowed()->create(['name' => 'lookup', 'description' => 'Finds a customer by email']);
    $this->client->shouldReceive('callTool')->andReturn(McpToolResultData::fromEnvelope(['content' => [['type' => 'text', 'text' => 'ok']]]));

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->text)->toBe('ok')
        ->and($result->isError)->toBeFalse();
    Event::assertDispatched(McpToolInvoked::class, fn (McpToolInvoked $event): bool => $event->error === null
        && $event->agentId === $this->agent->id
        && $event->workspaceId === (int) $this->agent->workspace_id
        && $event->serverId === $this->server->id
        && $event->serverSlug === 'crm'
        && $event->serverName === 'CRM'
        && $event->connectorSlug === null
        && $event->toolName === 'lookup'
        && $event->toolDescription === 'Finds a customer by email');
});

it('logs argument keys but never their values', function (): void {
    $this->client->shouldReceive('callTool')->andReturn(McpToolResultData::fromEnvelope(['content' => [['type' => 'text', 'text' => 'ok']]]));
    Log::spy();

    app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'MCP tool dispatched'
        && $context['arg_keys'] === ['email', 'phone']
        && ! Str::contains((string) json_encode($context), 'jane@example.com'));
});

it('hides transport details from the model and the logs', function (): void {
    $this->client->shouldReceive('callTool')->andThrow(
        new McpException('Could not reach MCP server: cURL error 7 for https://crm.example.test/mcp?token=SECRET-TOKEN'),
    );
    Log::spy();

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->not->toContain('SECRET-TOKEN')
        ->and($result->text)->not->toContain('crm.example.test')
        ->and($result->text)->toContain('could not be reached');

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        $encoded = (string) json_encode($context);

        return $message === 'MCP tool dispatch failed'
            && $context['exception'] === McpException::class
            && ! Str::contains($encoded, 'SECRET-TOKEN')
            && ! Str::contains($encoded, 'jane@example.com');
    });

    // The full message stays with the workspace: the counters listener
    // stores it as the server's last_error.
    Event::assertDispatched(McpToolInvoked::class, fn (McpToolInvoked $event): bool => $event->error === 'Could not reach MCP server: cURL error 7 for https://crm.example.test/mcp?token=SECRET-TOKEN');
});

it('tells the model only what is safe of a failed call', function (Throwable $failure, string $shown, string $hidden): void {
    $this->client->shouldReceive('callTool')->andThrow($failure);

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toContain($shown)
        ->and($result->text)->not->toContain($hidden);
})->with([
    'an error the server reported: passed through' => [McpException::reportedByServer('MCP error from tools/call: Unknown customer email', 'Unknown customer email'), 'Unknown customer email', 'MCP error from tools/call'],
    'an HTTP failure: by status only' => [new McpException('MCP server returned HTTP 502: <html>internal stack trace</html>', 502), 'HTTP 502', 'stack trace'],
    'anything unexpected: a generic message' => [new RuntimeException('SQLSTATE[42P01] relation "secret_table" does not exist'), 'failed unexpectedly', 'secret_table'],
]);

it('refuses a name without both a server slug and a tool, without calling the server', function (string $name): void {
    $this->client->shouldNotReceive('callTool');

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named($name, $this->args));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toBe("Tool error: Tool \"{$name}\" is not a recognized MCP tool.");
})->with(['mcp__crm', 'mcp____lookup', 'mcp__crm__']);

it('marks a failure the tool reports itself as a tool error', function (): void {
    $this->client->shouldReceive('callTool')->andReturn(McpToolResultData::fromEnvelope([
        'content' => [['type' => 'text', 'text' => 'no such customer']],
        'isError' => true,
    ]));

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->text)->toBe('Tool error: no such customer')
        ->and($result->isError)->toBeTrue();
});

it('refuses a tool the workspace blocked, without calling the server', function (Closure $block, string $name): void {
    // The catalog hides blocked tools, but the model can replay one from the
    // turn history.
    $block($this->agent, $this->server);
    $this->client->shouldNotReceive('callTool');

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named($name, $this->args));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toBe("Tool error: Tool \"{$name}\" is blocked.");
    Event::assertNotDispatched(McpToolInvoked::class);
})->with([
    'an own tool of a custom server' => [fn (Agent $agent, AgentMcpServer $server): McpTool => McpTool::factory()->for($server, 'mcpServer')->blocked()->create(['name' => 'lookup']), 'mcp__crm__lookup'],
    'a preset tool by its workspace membership' => [function (Agent $agent): void {
        $notion = AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create();
        $notion->connectorTools()->attach(ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'search'])->id, ['permission' => McpToolPermission::Blocked->value]);
    }, 'mcp__notion__search'],
]);

it('refuses a server the agent may not call, without calling it', function (Closure $billing): void {
    $billing($this->agent);
    $this->client->shouldNotReceive('callTool');

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__billing__charge', $this->args));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toBe('Tool error: MCP server "billing" is not attached to this agent or is disabled.');
    Event::assertNotDispatched(McpToolInvoked::class);
})->with([
    'a server of the workspace, not attached' => fn (Agent $agent): AgentMcpServer => AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'billing']),
    'attached but disabled' => fn (Agent $agent): AgentMcpServer => AgentMcpServer::factory()->disabled()->attachedTo($agent)->create(['slug' => 'billing']),
]);
