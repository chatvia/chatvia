<?php

declare(strict_types=1);

namespace App\Admin\Settings\Jobs;

use App\Admin\Settings\Actions\ReindexEmbeddingsAction;
use App\Admin\Settings\Listeners\ReindexForNewEmbeddingModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Starts a re-index of everything stored as vectors. Dispatched after the
 * response once the embedding model changed ({@see ReindexForNewEmbeddingModel}),
 * so the new model is saved before any part of the work runs.
 */
class ReindexEmbeddingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReindexEmbeddingsAction $action): void
    {
        $action->execute();
    }
}
