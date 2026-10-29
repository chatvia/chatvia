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
 * tokens: the dispatcher sends only the arguments the tool's schema declares,
 * logs argument keys only and never hands raw transport errors to the model;
 * the full message stays with the workspace, on the event the counters
 * listener stores as the server's last_error. A call it refuses (a malformed
 * name, a blocked tool the model replays from the turn history, a server the
 * agent may not call, a capability without a license, arguments that do not
 * fit the schema) never reaches the server.
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

it('refuses a call it may not make, without calling the server', function (Closure $setup, string $problem): void {
    [$name, $arguments] = $setup($this->agent, $this->server) + [1 => $this->args];
    $this->client->shouldNotReceive('callTool');

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named($name, $arguments));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toContain($problem);
    Event::assertNotDispatched(McpToolInvoked::class);
})->with([
    'a name with only a server slug' => [fn (): array => ['mcp__crm'], 'Tool error: Tool "mcp__crm" is not a recognized MCP tool.'],
    'a name without a tool' => [fn (): array => ['mcp__crm__'], 'Tool error: Tool "mcp__crm__" is not a recognized MCP tool.'],
    'a name without a server slug' => [fn (): array => ['mcp____lookup'], 'Tool error: Tool "mcp____lookup" is not a recognized MCP tool.'],
    'an own tool the workspace blocked' => [function (Agent $agent, AgentMcpServer $server): array {
        McpTool::factory()->for($server, 'mcpServer')->blocked()->create(['name' => 'lookup']);

        return ['mcp__crm__lookup'];
    }, 'Tool error: Tool "mcp__crm__lookup" is blocked.'],
    'a preset tool blocked by its workspace membership' => [function (Agent $agent): array {
        $notion = AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create();
        $notion->connectorTools()->attach(ConnectorTool::factory()->create(['connector_slug' => 'notion', 'name' => 'search'])->id, ['permission' => McpToolPermission::Blocked->value]);

        return ['mcp__notion__search'];
    }, 'Tool error: Tool "mcp__notion__search" is blocked.'],
    'a server of the workspace, not attached' => [function (Agent $agent): array {
        AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'billing']);

        return ['mcp__billing__charge'];
    }, 'Tool error: MCP server "billing" is not attached to this agent or is disabled.'],
    'an attached but disabled server' => [function (Agent $agent): array {
        AgentMcpServer::factory()->disabled()->attachedTo($agent)->create(['slug' => 'billing']);

        return ['mcp__billing__charge'];
    }, 'Tool error: MCP server "billing" is not attached to this agent or is disabled.'],
    'a capability tool without a license key' => [function (Agent $agent): array {
        config(['chatvia.license.licensed' => false]);
        AgentMcpServer::factory()->builtin('charts')->attachedTo($agent)->create();

        return ['mcp__charts__render-chart', '{}'];
    }, 'needs a ChatVia.ai license key'],
    'arguments that do not fit the input schema' => [function (Agent $agent, AgentMcpServer $server): array {
        McpTool::factory()->for($server, 'mcpServer')->allowed()->create([
            'name' => 'lookup',
            'input_schema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']], 'required' => ['limit']],
        ]);

        return ['mcp__crm__lookup', '{"limit":"ten"}'];
    }, '"limit" must be of type integer'],
]);

it('sends only the declared arguments and logs their keys but never their values', function (): void {
    McpTool::factory()->for($this->server, 'mcpServer')->allowed()->create([
        'name' => 'lookup',
        'input_schema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string'], 'phone' => ['type' => 'string']], 'required' => ['email']],
    ]);
    $this->client->shouldReceive('callTool')
        ->withArgs(fn (AgentMcpServer $server, string $tool, array $arguments): bool => $arguments === ['email' => 'jane@example.com', 'phone' => '+4712345678'])
        ->once()
        ->andReturn(McpToolResultData::fromEnvelope(['content' => [['type' => 'text', 'text' => 'ok']]]));
    Log::spy();

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', json_encode(['email' => 'jane@example.com', 'phone' => '+4712345678', 'is_admin' => true], JSON_THROW_ON_ERROR)));

    expect($result->text)->toBe('ok')
        ->and($result->isError)->toBeFalse();
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'MCP tool dispatched'
        && $context['arg_keys'] === ['email', 'phone']
        && ! Str::contains((string) json_encode($context), 'jane@example.com'));
    Event::assertDispatched(McpToolInvoked::class, fn (McpToolInvoked $event): bool => $event->error === null);
});

it('tells the model and the logs only what is safe of a failed call, keeping the full error for the workspace', function (Throwable $failure, string $shown, array $hidden): void {
    $this->client->shouldReceive('callTool')->andThrow($failure);
    Log::spy();

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toContain($shown);
    foreach ($hidden as $secret) {
        expect($result->text)->not->toContain($secret);
    }
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'MCP tool dispatch failed'
        && $context['exception'] === $failure::class
        && ! Str::contains((string) json_encode($context), [...$hidden, 'jane@example.com']));
    Event::assertDispatched(McpToolInvoked::class, fn (McpToolInvoked $event): bool => $event->error === $failure->getMessage());
})->with([
    'a transport failure: a generic message' => [new McpException('Could not reach MCP server: cURL error 7 for https://crm.example.test/mcp?token=SECRET-TOKEN'), 'could not be reached', ['SECRET-TOKEN', 'crm.example.test']],
    'an error the server reported: passed through' => [McpException::reportedByServer('MCP error from tools/call: Unknown customer email', 'Unknown customer email'), 'Unknown customer email', ['MCP error from tools/call']],
    'an HTTP failure: by status only' => [new McpException('MCP server returned HTTP 502: <html>internal stack trace</html>', 502), 'HTTP 502', ['stack trace']],
    'anything unexpected: a generic message' => [new RuntimeException('SQLSTATE[42P01] relation "secret_table" does not exist'), 'failed unexpectedly', ['secret_table']],
]);
