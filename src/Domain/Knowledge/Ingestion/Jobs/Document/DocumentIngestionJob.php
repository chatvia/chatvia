<?php

namespace Domain\Knowledge\Ingestion\Jobs\Document;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestDocumentAction;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Parses an uploaded document and starts its chunk batch. See
 * {@see IngestDocumentAction}. Word files run on their own queue because
 * reading them takes longer; the other formats share the OCR queue.
 */
// One try: a failed parse goes straight to failed(), which marks the file failed.
#[Tries(1)]
// Below the `redis-long` connection's `retry_after`, so no second worker picks it up mid-run.
#[Timeout(1800)]
// Longer than a run, so a worker that dies mid-run cannot hold the lock for good.
#[UniqueFor(3600)]
// A file deleted before the job runs needs no ingestion.
#[DeleteWhenMissingModels]
class DocumentIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public readonly SourceFile $sourceFile, public readonly Agent $agent)
    {
        // Chosen per file, so an attribute cannot name it.
        $this->onQueue($sourceFile->isWordFile() ? 'ingestion.word' : 'ingestion.pdf');
    }

    public function handle(IngestDocumentAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->sourceFile, $this->agent, $this->job->getJobId());
    }

    public function uniqueId(): string
    {
        return 'ingestion:files:'.$this->sourceFile->id;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'source_file:'.$this->sourceFile->id,
            'ingestion_job',
        ];
    }

    public function failed(Throwable $exception): void
    {
        app(MarkSourceFailedAction::class)->execute($this->sourceFile, $this->agent, $exception);
    }
}
