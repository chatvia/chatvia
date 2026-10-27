<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Integration\Connector\Data\Agent\AgentCapabilityRowData;
use Domain\Integration\Connector\Data\Agent\CapabilityProviderData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Capabilities page: every first-party capability
 * (Interactive Charts, Forms, …), whether this agent has it on, and whether
 * which connector it works through (if any) is connected and in use, and
 * for Image and Video generation whether chatters may pick the model.
 */
class AgentCapabilitiesController
{
    public function __invoke(Agent $agent, ConnectorCatalog $catalog, CapabilityProvider $providers, OfferedMediaModels $mediaModels): Response
    {
        $capabilities = $catalog->capabilities();

        // Capability slug => the workspace's server id, for those connected.
        $serverIdBySlug = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->whereIn('connector_slug', $capabilities->keys())
            ->pluck('id', 'connector_slug');

        $attachedIds = $agent->mcpServers()->pluck('mcp_servers.id')->flip();

        $rows = $capabilities->values()->map(function (ConnectorData $capability) use ($agent, $catalog, $providers, $serverIdBySlug, $attachedIds, $mediaModels): AgentCapabilityRowData {
            $connected = $providers->connected($agent, $capability);
            $kind = match ($capability->slug) {
                'image-generation' => GeneratedMediaKind::Image,
                'video-generation' => GeneratedMediaKind::Video,
                default => null,
            };

            return new AgentCapabilityRowData(
                connector: $capability,
                enabled: ($serverId = $serverIdBySlug->get($capability->slug)) !== null && $attachedIds->has($serverId),
                ready: $providers->isMet($agent, $capability),
                providers: collect($capability->worksThrough)
                    ->map(fn (string $slug): ?ConnectorData => $catalog->find($slug))
                    ->filter()
                    ->map(fn (ConnectorData $connector): CapabilityProviderData => new CapabilityProviderData($connector, in_array($connector->slug, $connected, true)))
                    ->values()
                    ->all(),
                provider: $providers->providerFor($agent, $capability),
                model_choice_allowed: $kind instanceof GeneratedMediaKind && $mediaModels->isAllowed($kind),
                model_choice_enabled: $kind instanceof GeneratedMediaKind && $mediaModels->isEnabled($agent, $kind),
            );
        });

        return Inertia::render('agents/Capabilities/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'capabilities' => AgentCapabilityRowData::collect($rows),
        ]);
    }
}
