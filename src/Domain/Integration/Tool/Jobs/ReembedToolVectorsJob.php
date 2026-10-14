<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Jobs;

use Domain\Integration\Tool\Actions\Embedding\ReembedToolVectorsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds every tool again with the embedding model now in use. Dispatched
 * after a change of the embedding provider or model (Settings → AI models).
 */
class ReembedToolVectorsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReembedToolVectorsAction $action): void
    {
        $action->execute();
    }
}
