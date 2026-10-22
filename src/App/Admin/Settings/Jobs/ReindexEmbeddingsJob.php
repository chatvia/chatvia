<?php

declare(strict_types=1);

namespace App\Admin\Settings\Jobs;

use App\Admin\Settings\Actions\ReindexEmbeddingsAction;
use App\Admin\Settings\Listeners\ReindexForNewEmbeddingModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/**
 * Starts a re-index of everything stored as vectors, on a worker. Queued once
 * the new embedding model is saved ({@see ReindexForNewEmbeddingModel}); the
 * worker applies the saved settings before it runs, so the work always uses
 * the new model.
 */
// The re-index batch runs on this queue too, on the long supervisor.
#[Queue('ingestion.chunks')]
// One try: a retry would start a second batch next to the first.
#[Tries(1)]
class ReindexEmbeddingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReindexEmbeddingsAction $action): void
    {
        $action->execute();
    }
}
