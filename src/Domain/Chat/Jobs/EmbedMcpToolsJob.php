<?php

namespace Domain\Chat\Jobs;

use Domain\Chat\Actions\Embeddings\EmbedMcpToolsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
