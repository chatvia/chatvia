<?php

namespace Domain\Conversation\Jobs;

use Domain\Conversation\Actions\Embeddings\EmbedMcpToolsAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $userId = AgentMcpServer::query()->whereKey($this->mcpServerId)->value('user_id');

        return $userId !== null ? (int) $userId : null;
    }
}
