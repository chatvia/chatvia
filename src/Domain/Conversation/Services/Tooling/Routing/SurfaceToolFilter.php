<?php

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;
use Illuminate\Support\Str;

/**
 * Drops tools whose connector pins itself to surfaces other than the one this
 * conversation runs on. A connector may declare a `surfaces` allow-list in its
 * catalog class (e.g. Interactive Charts → ['hosted_chat']); when set,
 * its tools are withheld from the model on every other surface so it never
 * tries to call a tool whose artifact that surface can't render.
 *
 * Connectors with no `surfaces` key (the default) are unrestricted and always
 * kept. The surface is the conversation's persisted {@see ConversationSource}.
 */
class SurfaceToolFilter
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    /**
     * @param  array<int, ToolEntry>  $entries
     * @return array<int, ToolEntry>
     */
    public function apply(array $entries, ?string $conversationExternalId): array
    {
        if ($entries === [] || $conversationExternalId === null) {
            return $entries;
        }

        // Connectors that pin themselves to a subset of surfaces. The common
        // case is none — bail before resolving the surface so an unrestricted
        // catalog pays nothing.
        $restricted = $this->catalog->all()
            ->filter(static fn (ConnectorData $c): bool => $c->surfaces !== null);

        if ($restricted->isEmpty()) {
            return $entries;
        }

        $surface = $this->resolveSurface($conversationExternalId);

        if (! $surface instanceof ConversationSource) {
            return $entries;
        }

        // Tool-name prefixes (`mcp__{server_slug}__`) to drop: every restricted
        // connector whose allow-list does NOT include the current surface. The
        // server slug is derived the same way provisioning does
        // ({@see AgentMcpServer::makeSlug}), so it matches the compiled tool name.
        $prefixes = $restricted
            ->reject(static fn (ConnectorData $c): bool => in_array($surface->value, $c->surfaces ?? [], true))
            ->map(static fn (ConnectorData $c): string => ToolName::mcpPrefix(AgentMcpServer::makeSlug($c->slug)))
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

    private function resolveSurface(string $conversationExternalId): ?ConversationSource
    {
        return Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->first(['source'])?->source;
    }
}
