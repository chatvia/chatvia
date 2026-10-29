<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Account\AccountConnectorRowData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorRowKind;
use Domain\Integration\Connector\Queries\AccountConnectorsQuery;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Settings → Connectors lists the workspace's custom servers after the
 * catalog, never another workspace's. ConnectorControllerTest covers the
 * featured listing.
 */
it('lists only the workspace\'s own custom servers after the catalog, oldest first', function (): void {
    $owner = User::factory()->create();
    $first = AgentMcpServer::factory()->forOwner($owner)->create();
    $second = AgentMcpServer::factory()->forOwner($owner)->create(['created_at' => now()->addMinute()]);
    AgentMcpServer::factory()->create();

    $custom = app(AccountConnectorsQuery::class)->get($owner)->custom->toCollection();

    expect($custom->map(fn (AccountConnectorRowData $row): ?string => $row->server?->external_id)->all())
        ->toBe([$first->external_id, $second->external_id])
        ->and($custom->every(fn (AccountConnectorRowData $row): bool => $row->kind === ConnectorRowKind::Custom && ! $row->connector instanceof ConnectorData))->toBeTrue();
});
