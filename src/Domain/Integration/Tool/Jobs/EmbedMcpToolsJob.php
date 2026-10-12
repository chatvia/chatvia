<?php

namespace Domain\Integration\Tool\Jobs;

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Actions\Embedding\EmbedMcpToolsAction;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds a custom MCP server's own `mcp_tools` rows after a catalog sync or a
 * server rename. Only rows missing an embedding are touched; the tokens bill
 * to the server's workspace.
 */
class EmbedMcpToolsJob implements AttributesTokenUsage, ShouldQueue
{
    use Queueable;
    use TracksTokenUsage;

    public int $tries = 2;

    public function __construct(public int $mcpServerId) {}

    public function handle(EmbedMcpToolsAction $action): void
    {
        $action->execute($this->mcpServerId);
    }

    public function tokenWorkspaceId(): ?int
    {
        $userId = AgentMcpServer::query()->whereKey($this->mcpServerId)->value('workspace_id');

        return $userId !== null ? (int) $userId : null;
    }
}
