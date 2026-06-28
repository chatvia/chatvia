<?php

namespace Domain\Knowledge\Ingestion\Jobs\TextFile;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestTextFileAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingest a text SourceFile (.txt, .md/.markdown, and Pandoc-extracted ebooks
 * .epub/.fb2) by chunking its content directly via chunk.fit — the document/OCR
 * pipeline (DocumentIngestionJob) is bypassed because the text is already
 * chunk-ready. Mirrors DocumentIngestionJob's lifecycle handling; the per-chunk
 * embedding work is shared via DocumentChunkIngestionJob.
 */
class TextFileIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public $tries = 1;

    public $backoff = 10;

    public $timeout = 600;

    public $uniqueFor = 3600;

    public function __construct(public SourceFile $sourceFile, public Agent $agent)
    {
        $this->onQueue('ingestion.text');
    }

    public function handle(IngestTextFileAction $action): void
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

    public function uniqueFor(): int
    {
        return $this->uniqueFor;
    }

    public function tags(): array
    {
        return [
            'source_file:'.$this->sourceFile->id,
            'ingestion_job',
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('TextFileIngestionJob failed', [
            'source_file_id' => $this->sourceFile->id,
            'job_id' => $this->job?->getJobId(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->sourceFile->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
        ]);
    }

    public function timeout(): void
    {
        Log::warning('TextFileIngestionJob timed out', [
            'source_file_id' => $this->sourceFile->id,
            'job_id' => $this->job?->getJobId() ?? $this->job?->uuid() ?? 'unknown',
        ]);

        $this->sourceFile->update([
            'status' => 'failed',
            'failure_reason' => 'Job timed out after '.$this->timeout.' seconds',
        ]);
    }
}
