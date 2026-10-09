<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Actions\Files\CancelFileIngestionAction;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * CancelFileIngestionAction stops a file's chunking batch when the file is deleted
 * mid-ingestion. It must tolerate a batch that was already pruned, and still
 * find media batches whose id was stored in the older column.
 */
function storedBatchId(): string
{
    return app(BatchRepository::class)->store(Bus::batch([]))->id;
}

it('cancels the batch stored in internal_chunking_batch_id', function (): void {
    $batchId = storedBatchId();
    $file = AgentFileFactory::new()->create(['internal_chunking_batch_id' => $batchId]);

    app(CancelFileIngestionAction::class)->execute($file);

    expect(Bus::findBatch($batchId)->cancelled())->toBeTrue();
});

it('cancels a media batch whose id was stored in internal_ingestion_job_id', function (): void {
    $batchId = storedBatchId();
    $file = AgentFileFactory::new()->create([
        'internal_chunking_batch_id' => null,
        'internal_ingestion_job_id' => $batchId,
    ]);

    app(CancelFileIngestionAction::class)->execute($file);

    expect(Bus::findBatch($batchId)->cancelled())->toBeTrue();
});

it('does nothing when the batch no longer exists', function (): void {
    $file = AgentFileFactory::new()->create([
        'internal_chunking_batch_id' => (string) Str::uuid(),
        'internal_ingestion_job_id' => (string) Str::uuid(),
    ]);

    app(CancelFileIngestionAction::class)->execute($file);
})->throwsNoExceptions();
