<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Jobs;

use Domain\Integration\Tool\Actions\Embedding\EmbedConnectorToolsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds the shared `connector_tools` catalog for one connector_slug.
 * Dispatched by SyncMcpServerCatalogAction (shared-catalog path) and
 * SyncBuiltinConnectorsAction. NULL-guarded, so it's a no-op once a
 * connector's tools are already embedded.
 */
class EmbedConnectorToolsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $connectorSlug) {}

    public function handle(EmbedConnectorToolsAction $action): void
    {
        $action->execute($this->connectorSlug);
    }
}
