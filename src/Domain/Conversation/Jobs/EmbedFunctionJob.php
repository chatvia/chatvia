<?php

namespace Domain\Conversation\Jobs;

use Domain\Conversation\Actions\Embeddings\EmbedAgentFunctionAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedFunctionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $functionId) {}

    public function handle(EmbedAgentFunctionAction $action): void
    {
        $action->execute($this->functionId);
    }
}
