<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Jobs;

use Domain\Integration\Tool\Actions\Embedding\EmbedMcpToolsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds a custom MCP server's own `mcp_tools` rows after a catalog sync or a
 * server rename. Only rows missing an embedding are touched; the tokens bill
 * to the server's workspace.
 */
class EmbedMcpToolsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $mcpServerId) {}

    public function handle(EmbedMcpToolsAction $action): void
    {
        $action->execute($this->mcpServerId);
    }
}
