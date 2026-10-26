<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;
use Illuminate\Support\Str;

/**
 * Drops the tools of a capability the install does not offer right now
 * (`ConnectorData::$available`): Image generation while no image service is
 * picked, Audio generation while Read aloud is off. An agent that turned one
 * on before an admin switched the service off keeps its switch, but the
 * model is never offered a tool that can only refuse. Same shape as
 * {@see SurfaceToolFilter}.
 */
class UnavailableToolFilter
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    /**
     * @param  array<int, ToolEntry>  $entries
     * @return array<int, ToolEntry>
     */
    public function apply(array $entries): array
    {
        if ($entries === []) {
            return $entries;
        }

        $prefixes = $this->catalog->all()
            ->reject(static fn (ConnectorData $connector): bool => $connector->available)
            ->map(static fn (ConnectorData $connector): string => ToolName::mcpPrefix(AgentMcpServer::makeSlug($connector->slug)))
            ->values()
            ->all();

        if ($prefixes === []) {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            static fn (ToolEntry $entry): bool => ! Str::startsWith($entry->name, $prefixes),
        ));
    }
}
