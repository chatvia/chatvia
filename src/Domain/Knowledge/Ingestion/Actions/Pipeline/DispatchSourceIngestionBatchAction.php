<?php

namespace Domain\Knowledge\Ingestion\Actions\Pipeline;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

/**
 * The shared tail of every chunked ingestion (documents, text files, media and
 * texts): runs the source's per-chunk jobs as one Bus batch.
 *
 * It queues the source's summary, marks the source as ingesting with the
 * number of chunks to expect (the progress bar divides by it), and dispatches
 * the batch. One failed chunk must not cancel the others (`allowFailures`).
 * The batch's `finally` step runs {@see CompleteSourceIngestionAction}, which
 * marks the source as ingested and sends the final progress event.
 *
 * With no chunk jobs at all (for example an empty text file) the source is
 * completed straight away: a batch without jobs would never call `finally`
 * and the page would wait forever.
 */
class DispatchSourceIngestionBatchAction
{
    public function __construct(
        private readonly CompleteSourceIngestionAction $complete,
    ) {}

    /**
     * @param  Collection<array-key, covariant ShouldQueue>  $jobs  the per-chunk jobs, in reading order
     * @return Batch|null null when there was nothing to chunk
     */
    public function execute(SourceFile|SourceText $source, Agent $agent, Collection $jobs): ?Batch
    {
        if ($jobs->isEmpty()) {
            $source->update(['expected_chunks' => 0]);
            $this->complete->execute($source, $agent->external_id);

            return null;
        }

        SummaryIngestionJob::dispatch($source);

        $source->update([
            'status' => SourceStatus::Processing,
            'expected_chunks' => $jobs->count(),
        ]);

        // The callback is serialized with the batch, so it captures scalars
        // only and loads the source again when it runs.
        $sourceType = $source->getMorphClass();
        $sourceId = $source->getKey();
        $agentExternalId = $agent->external_id;

        $batch = Bus::batch($jobs->all())
            ->name('Ingestion '.$sourceType.' ID: '.$sourceId)
            ->allowFailures()
            ->finally(static function () use ($sourceType, $sourceId, $agentExternalId): void {
                $source = Relation::getMorphedModel($sourceType)::find($sourceId);

                // The source may have been deleted while it was being chunked.
                if ($source instanceof SourceFile || $source instanceof SourceText) {
                    app(CompleteSourceIngestionAction::class)->execute($source, $agentExternalId);
                }
            })
            ->dispatch();

        $source->update(['internal_chunking_batch_id' => $batch->id]);

        return $batch;
    }
}
