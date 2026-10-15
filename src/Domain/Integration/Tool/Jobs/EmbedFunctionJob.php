<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Jobs;

use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Actions\Embedding\EmbedAgentFunctionAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Embeds one webhook function so the hybrid tool search can rank it.
 * Dispatched by the AgentFunction observer when a function is created or its
 * name/description changes; the tokens bill to the function's workspace.
 */
// One retry for a passing embedding-service error.
#[Tries(2)]
class EmbedFunctionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $functionId) {}

    public function handle(EmbedAgentFunctionAction $action): void
    {
        $action->execute($this->functionId);
    }
}
