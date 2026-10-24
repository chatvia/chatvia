<?php

declare(strict_types=1);

use App\Mcp\Servers\AccountAnalyticsServer;
use App\Mcp\Servers\ChartsServer;
use Domain\Integration\Mcp\Contracts\BuiltinToolDefinitions;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Illuminate\Database\Eloquent\Builder;

/**
 * The deploy step `chatvia:mcp:sync-builtin` catalogs the real built-in
 * servers: each analytics-chat server gets a global row with every tool its
 * code defines, embedded inline unless --queue is given. The sync rules
 * themselves are tested on SyncBuiltinConnectorsAction.
 */
it('catalogs the built-in servers as global rows with every tool they define, indexed', function (): void {
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();

    $globals = AgentMcpServer::query()->whereNull('workspace_id')->get()->keyBy('slug');
    $defined = fn (string $server): int => count(app(BuiltinToolDefinitions::class)->forServer($server));
    $unindexed = ConnectorTool::query()
        ->whereIn('connector_slug', ['account-analytics', 'charts'])
        ->where(fn (Builder $query): Builder => $query->whereNull('embedding')->orWhereNull('search_text'));

    expect($globals->keys()->sort()->values()->all())->toBe(['account-analytics', 'charts'])
        ->and($globals['account-analytics']->category)->toBe(McpServerCategory::Analytics)
        ->and($globals['account-analytics']->connectorTools()->count())->toBe($defined(AccountAnalyticsServer::class))
        ->and($globals['charts']->category)->toBe(McpServerCategory::Builtin)
        ->and($globals['charts']->connectorTools()->count())->toBe($defined(ChartsServer::class))
        ->and($unindexed->exists())->toBeFalse();
});
