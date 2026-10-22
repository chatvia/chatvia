<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Services;

use Domain\Knowledge\Embedding\Actions\Reindex\StartEmbeddingReindexAction;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * The last re-index of the stored vectors ({@see StartEmbeddingReindexAction}),
 * by its job batch: whether it still runs and how far it got, for the admin
 * panel. Only the batch id is kept; the progress is the batch's own.
 */
final class EmbeddingReindexStatus
{
    private const string KEY = 'knowledge:embedding-reindex:batch';

    public function remember(Batch $batch): void
    {
        Cache::forever(self::KEY, $batch->id);
    }

    public function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /** The last re-index started, null when none was or its batch was pruned. */
    public function latest(): ?Batch
    {
        $id = Cache::get(self::KEY);

        return filled($id) ? Bus::findBatch($id) : null;
    }

    /** The last re-index while it still runs. */
    public function running(): ?Batch
    {
        $batch = $this->latest();

        return $batch instanceof Batch && self::runs($batch) ? $batch : null;
    }

    /** Whether a re-index batch still has parts to run. */
    public static function runs(Batch $batch): bool
    {
        return ! $batch->finished() && ! $batch->cancelled();
    }
}
