<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Account\AccountConnectorRowData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorRowKind;
use Domain\Integration\Connector\Queries\AccountConnectorsQuery;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Settings → Connectors lists every featured catalog entry (with the
 * workspace's server when it connected one) and then the workspace's custom
 * servers, never another workspace's.
 */
it('lists every featured connector, with the connected server filled in', function (): void {
    $owner = User::factory()->create();
    $featuredSlugs = app(ConnectorCatalog::class)->connectors()->keys();
    $connectedSlug = $featuredSlugs->first();
    $server = AgentMcpServer::factory()->forOwner($owner)->preset($connectedSlug)->create();

    $result = app(AccountConnectorsQuery::class)->get($owner);

    $featured = $result->featured->toCollection();
    $connectedRow = $featured->first(fn (AccountConnectorRowData $row): bool => $row->connector?->slug === $connectedSlug);

    expect($featured)->toHaveCount($featuredSlugs->count())
        ->and($featured->every(fn (AccountConnectorRowData $row): bool => $row->kind === ConnectorRowKind::Featured))->toBeTrue()
        ->and($connectedRow?->server?->external_id)->toBe($server->external_id)
        ->and($featured->filter(fn (AccountConnectorRowData $row): bool => $row->server instanceof AgentMcpServerData))->toHaveCount(1);
});

it('lists the workspace\'s custom servers after the catalog, oldest first', function (): void {
    $owner = User::factory()->create();
    $first = AgentMcpServer::factory()->forOwner($owner)->create();
    $second = AgentMcpServer::factory()->forOwner($owner)->create(['created_at' => now()->addMinute()]);
    AgentMcpServer::factory()->create();

    $custom = app(AccountConnectorsQuery::class)->get($owner)->custom->toCollection();

    expect($custom->map(fn (AccountConnectorRowData $row): ?string => $row->server?->external_id)->all())
        ->toBe([$first->external_id, $second->external_id])
        ->and($custom->every(fn (AccountConnectorRowData $row): bool => $row->kind === ConnectorRowKind::Custom && ! $row->connector instanceof ConnectorData))->toBeTrue();
});
