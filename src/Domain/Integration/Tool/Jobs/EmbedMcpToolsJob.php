<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Jobs;

use Domain\Integration\Tool\Actions\Embedding\EmbedMcpToolsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Embeds a custom MCP server's own `mcp_tools` rows after a catalog sync or a
 * server rename. Only rows missing an embedding are touched; the tokens bill
 * to the server's workspace.
 */
// Covers a failure before the Action's own try/catch (a lost connection);
// embedding-service errors are swallowed and logged by the Action itself.
// Only rows missing an embedding are touched, so a retry is safe.
#[Tries(2)]
class EmbedMcpToolsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $mcpServerId) {}

    public function handle(EmbedMcpToolsAction $action): void
    {
        $action->execute($this->mcpServerId);
    }
}
