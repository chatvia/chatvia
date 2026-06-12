<?php

namespace Domain\Conversation\Jobs;

use Domain\Conversation\Actions\Embeddings\EmbedMcpToolsAction;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds the shared `connector_tools` catalog for one connector_slug. Dispatched
 * by McpCatalog::refresh() (preset path) + SyncBuiltinMcpRegistry. NULL-guarded,
 * so it's a no-op once a connector's tools are already embedded.
 */
class EmbedConnectorToolsJob implements AttributesTokenUsage, ShouldQueue
{
    use Queueable;
    use TracksTokenUsage;

    public int $tries = 2;

    public function __construct(public string $connectorSlug) {}

    public function handle(EmbedMcpToolsAction $action): void
    {
        $action->executeForConnector($this->connectorSlug);
    }

    public function tokenWorkspaceId(): ?int
    {
        // The catalog is workspace-agnostic — embedding it isn't one workspace's
        // cost, so it isn't attributed to a workspace's usage ledger.
        return null;
    }
}
