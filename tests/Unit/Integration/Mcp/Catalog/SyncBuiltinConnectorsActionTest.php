<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Actions\Catalog\SyncBuiltinConnectorsAction;
use Domain\Integration\Mcp\Contracts\BuiltinToolDefinitions;
use Domain\Integration\Mcp\Data\Catalog\SyncBuiltinConnectorsData;
use Domain\Integration\Mcp\Data\Catalog\SyncedBuiltinConnectorData;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\Queue;

/**
 * The deploy-time builtin sync, with the code-defined tool definitions
 * stubbed: global builtins get a global row, capability servers do not,
 * every workspace server of a builtin gets new tools, and every agent's
 * cached tool pool is dropped.
 */
beforeEach(function (): void {
    Queue::fake();

    config([
        'mcp.builtin' => [[
            'slug' => 'charts',
            'class' => 'ChartsServerFixture',
            'category' => 'builtin',
            'name' => 'Interactive charts',
            'description' => 'Charts.',
        ]],
        'mcp.capabilities' => [[
            'slug' => 'forms',
            'class' => 'FormsServerFixture',
        ]],
    ]);

    $this->app->instance(BuiltinToolDefinitions::class, new class implements BuiltinToolDefinitions
    {
        public function forServer(string $serverClass): array
        {
            return McpToolDefinitionData::listFrom(match ($serverClass) {
                'ChartsServerFixture' => [['name' => 'render-bar-chart', 'description' => 'bar', 'annotations' => ['readOnlyHint' => true]]],
                'FormsServerFixture' => [
                    ['name' => 'find-forms', 'description' => 'find', 'annotations' => ['readOnlyHint' => true]],
                    ['name' => 'render-form', 'description' => 'render'],
                ],
            });
        }
    });
});

it('catalogs global builtins with a global row and capability servers without one', function (): void {
    $result = app(SyncBuiltinConnectorsAction::class)->execute(new SyncBuiltinConnectorsData(queueEmbedding: true));

    expect(array_map(fn (SyncedBuiltinConnectorData $c): array => [$c->slug, $c->offeredAs, $c->toolsCount], $result->connectors))
        ->toBe([['charts', 'builtin', 1], ['forms', SyncedBuiltinConnectorData::CAPABILITY, 2]]);

    expect(AgentMcpServer::query()->whereNull('user_id')->pluck('slug')->all())->toBe(['charts'])
        ->and(AgentMcpServer::query()->whereNull('user_id')->sole()->url)->toEndWith('/mcp/charts');

    Queue::assertPushed(EmbedConnectorToolsJob::class, 2);
});

it('seeds a builtin write tool as Allow, the same as a workspace connect does', function (): void {
    app(SyncBuiltinConnectorsAction::class)->execute(new SyncBuiltinConnectorsData(queueEmbedding: true));

    expect(ConnectorTool::where('connector_slug', 'forms')->where('name', 'render-form')->value('default_permission'))
        ->toBe(McpToolPermission::Allow);
});

it('carries new tools to every workspace server of a builtin and drops every cached pool', function (): void {
    $workspaceForms = AgentMcpServer::create([
        'user_id' => User::factory()->create()->id,
        'connector_slug' => 'forms',
        'name' => 'Forms',
        'slug' => 'forms',
        'url' => 'https://chatvia.test/mcp/forms',
        'auth_type' => 'none',
    ]);
    $agent = Agent::factory()->create();
    $registry = $this->spy(ToolRegistry::class);

    $result = app(SyncBuiltinConnectorsAction::class)->execute(new SyncBuiltinConnectorsData(queueEmbedding: true));

    expect(McpServerTool::where('mcp_server_id', $workspaceForms->id)->count())->toBe(2)
        ->and($workspaceForms->fresh()->catalog_tools_count)->toBe(2)
        ->and($result->flushedAgents)->toBe(Agent::query()->count());

    $registry->shouldHaveReceived('forgetMany')->withArgs(fn (array $ids): bool => in_array($agent->id, $ids, true));
});
