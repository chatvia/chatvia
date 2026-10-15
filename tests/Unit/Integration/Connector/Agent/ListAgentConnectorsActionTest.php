<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\ListAgentConnectorsAction;
use Domain\Integration\Connector\Data\Agent\AgentConnectorRowData;
use Domain\Integration\Connector\Data\Custom\AgentCustomConnectorRowData;
use Domain\Integration\Connector\Enums\ConnectorRowState;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The agent Connectors page shows each featured connector in one of three
 * states for this agent — attached, connected to the workspace but not
 * attached, or not connected at all — and lists the workspace's custom
 * servers with their per-agent attachment. "Always on" only counts for a
 * server the agent actually uses.
 */
it('gives each featured connector its state for the agent', function (): void {
    $agent = Agent::factory()->create();
    [$attachedSlug, $connectedSlug, $availableSlug] = app(ConnectorCatalog::class)->connectors()->keys()->take(3)->all();

    $attached = AgentMcpServer::factory()->preset($attachedSlug)->attachedTo($agent, alwaysAvailable: true)->create();
    $connected = AgentMcpServer::factory()->preset($connectedSlug)->create(['workspace_id' => $agent->workspace_id]);
    // Another workspace connecting the third connector must not leak in.
    AgentMcpServer::factory()->preset($availableSlug)->create();

    $rows = app(ListAgentConnectorsAction::class)->execute($agent)->featured->toCollection()
        ->keyBy(fn (AgentConnectorRowData $row): string => $row->connector->slug);

    expect($rows[$attachedSlug]->state)->toBe(ConnectorRowState::Attached)
        ->and($rows[$attachedSlug]->always_available)->toBeTrue()
        ->and($rows[$attachedSlug]->server?->external_id)->toBe($attached->external_id)
        ->and($rows[$connectedSlug]->state)->toBe(ConnectorRowState::Unattached)
        ->and($rows[$connectedSlug]->always_available)->toBeFalse()
        ->and($rows[$connectedSlug]->server?->external_id)->toBe($connected->external_id)
        ->and($rows[$availableSlug]->state)->toBe(ConnectorRowState::Available)
        ->and($rows[$availableSlug]->server)->toBeNull();
});

it('lists the workspace\'s custom servers with their attachment to this agent', function (): void {
    $agent = Agent::factory()->create();
    $used = AgentMcpServer::factory()->attachedTo($agent)->create();
    $alwaysOn = AgentMcpServer::factory()->attachedTo($agent, alwaysAvailable: true)->create();
    $unused = AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id]);
    AgentMcpServer::factory()->create();

    $rows = app(ListAgentConnectorsAction::class)->execute($agent)->custom->toCollection()
        ->keyBy(fn (AgentCustomConnectorRowData $row): string => $row->external_id);

    expect($rows)->toHaveCount(3)
        ->and($rows[$used->external_id]->is_attached)->toBeTrue()
        ->and($rows[$used->external_id]->always_available)->toBeFalse()
        ->and($rows[$alwaysOn->external_id]->is_attached)->toBeTrue()
        ->and($rows[$alwaysOn->external_id]->always_available)->toBeTrue()
        ->and($rows[$unused->external_id]->is_attached)->toBeFalse()
        ->and($rows[$unused->external_id]->always_available)->toBeFalse();
});
