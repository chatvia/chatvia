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
// Covers a failure before the Action's own try/catch (a lost connection);
// embedding-service errors are swallowed and logged by the Action itself.
#[Tries(2)]
class EmbedFunctionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $functionId) {}

    public function handle(EmbedAgentFunctionAction $action): void
    {
        $action->execute($this->functionId);
    }
}
