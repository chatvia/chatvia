<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Jobs;

use Domain\Integration\Tool\Actions\Embedding\EmbedConnectorToolsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Embeds the shared `connector_tools` catalog for one connector_slug.
 * Dispatched by SyncMcpServerCatalogAction (shared-catalog path) and
 * SyncBuiltinConnectorsAction. NULL-guarded, so it's a no-op once a
 * connector's tools are already embedded.
 */
// Covers a failure before the Action's own try/catch (a lost connection);
// embedding-service errors are swallowed and logged by the Action itself.
// NULL-guarded, so a retry embeds only the tools still missing a vector.
#[Tries(2)]
class EmbedConnectorToolsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $connectorSlug) {}

    public function handle(EmbedConnectorToolsAction $action): void
    {
        $action->execute($this->connectorSlug);
    }
}
