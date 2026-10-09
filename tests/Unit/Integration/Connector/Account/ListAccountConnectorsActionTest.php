<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Account\ListAccountConnectorsAction;
use Domain\Integration\Connector\Data\Account\AccountConnectorRowData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorRowKind;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Settings → Connectors lists every featured catalog entry (with the
 * workspace's server when it connected one) and then the workspace's custom
 * servers. Servers belong to the workspace owner, so a teammate sees the same
 * list and never another workspace's servers.
 */
function accountConnectorServer(User $owner, ?string $connectorSlug, string $slug): AgentMcpServer
{
    return AgentMcpServer::create([
        'user_id' => $owner->id,
        'connector_slug' => $connectorSlug,
        'name' => ucfirst($slug),
        'slug' => $slug,
        'url' => 'https://mcp.example.test/'.$slug,
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

it('lists every featured connector, with the connected server filled in', function (): void {
    $owner = User::factory()->create();
    $featuredSlugs = app(ConnectorCatalog::class)->connectors()->keys();
    $connectedSlug = $featuredSlugs->first();
    $server = accountConnectorServer($owner, $connectedSlug, $connectedSlug);

    $result = app(ListAccountConnectorsAction::class)->execute($owner);

    $featured = $result->featured->toCollection();
    $connectedRow = $featured->first(fn (AccountConnectorRowData $row): bool => $row->connector?->slug === $connectedSlug);

    expect($featured)->toHaveCount($featuredSlugs->count())
        ->and($featured->every(fn (AccountConnectorRowData $row): bool => $row->kind === ConnectorRowKind::Featured))->toBeTrue()
        ->and($connectedRow?->server?->external_id)->toBe($server->external_id)
        ->and($featured->filter(fn (AccountConnectorRowData $row): bool => $row->server instanceof AgentMcpServerData))->toHaveCount(1);
});

it('lists the workspace\'s custom servers after the catalog, oldest first', function (): void {
    $owner = User::factory()->create();
    $first = accountConnectorServer($owner, null, 'first-custom');
    $second = accountConnectorServer($owner, null, 'second-custom');
    $second->forceFill(['created_at' => now()->addMinute()])->save();
    accountConnectorServer(User::factory()->create(), null, 'foreign-custom');

    $custom = app(ListAccountConnectorsAction::class)->execute($owner)->custom->toCollection();

    expect($custom->map(fn (AccountConnectorRowData $row): ?string => $row->server?->external_id)->all())
        ->toBe([$first->external_id, $second->external_id])
        ->and($custom->every(fn (AccountConnectorRowData $row): bool => $row->kind === ConnectorRowKind::Custom && ! $row->connector instanceof ConnectorData))->toBeTrue();
});

it('shows a teammate the workspace owner\'s servers', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    $server = accountConnectorServer($owner, null, 'team-custom');

    $custom = app(ListAccountConnectorsAction::class)->execute($teammate)->custom->toCollection();

    expect($custom)->toHaveCount(1)
        ->and($custom->first()->server?->external_id)->toBe($server->external_id);
});
