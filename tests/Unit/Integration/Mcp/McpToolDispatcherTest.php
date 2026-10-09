<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Mcp\Services\McpException;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Domain\Integration\Tool\Data\ToolCallData;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Tool arguments are visitor data and transport errors can carry URLs or
 * tokens: the dispatcher must log argument keys only and must never hand raw
 * transport errors to the model.
 */
beforeEach(function (): void {
    Event::fake([McpToolInvoked::class]);

    $this->agent = Agent::factory()->create();
    $server = AgentMcpServer::create([
        'user_id' => $this->agent->user_id,
        'name' => 'CRM',
        'slug' => 'crm',
        'url' => 'https://crm.example.test/mcp',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
    $this->agent->mcpServers()->attach($server->id);

    $this->client = Mockery::mock(McpClient::class);
    app()->instance(McpClient::class, $this->client);

    $this->args = json_encode(['email' => 'jane@example.com', 'phone' => '+4712345678']);
});

it('logs argument keys but never their values', function (): void {
    $this->client->shouldReceive('callTool')->andReturn(['content' => [['type' => 'text', 'text' => 'ok']]]);
    Log::spy();

    app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'MCP tool dispatched'
            && $context['arg_keys'] === ['email', 'phone']
            && ! str_contains((string) json_encode($context), 'jane@example.com');
    });
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
            && ! str_contains($encoded, 'SECRET-TOKEN')
            && ! str_contains($encoded, 'jane@example.com');
    });
});

it('passes an error the MCP server itself reported through to the model', function (): void {
    $this->client->shouldReceive('callTool')->andThrow(
        McpException::reportedByServer('MCP error from tools/call: Unknown customer email', 'Unknown customer email'),
    );

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->text)->toContain('Unknown customer email');
});

it('reports an HTTP failure by status only', function (): void {
    $this->client->shouldReceive('callTool')->andThrow(
        new McpException('MCP server returned HTTP 502: <html>internal stack trace</html>', 502),
    );

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->text)->toContain('HTTP 502')
        ->and($result->text)->not->toContain('stack trace');
});

it('gives a generic message for unexpected failures', function (): void {
    $this->client->shouldReceive('callTool')->andThrow(new RuntimeException('SQLSTATE[42P01] relation "secret_table" does not exist'));

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named('mcp__crm__lookup', $this->args));

    expect($result->text)->not->toContain('secret_table')
        ->and($result->text)->toContain('failed unexpectedly');
});

it('refuses a name without both a server slug and a tool, without calling the server', function (string $name): void {
    $this->client->shouldNotReceive('callTool');

    $result = app(McpToolDispatcher::class)->dispatch($this->agent, ToolCallData::named($name, $this->args));

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toBe("Tool error: Tool \"{$name}\" is not a recognized MCP tool.");
})->with(['mcp__crm', 'mcp____lookup', 'mcp__crm__']);
