<?php

namespace Domain\Ingestion\Jobs\Document;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Actions\Documents\IngestDocumentAction;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public $tries = 1;

    public $backoff = 10;

    public $timeout = 1800;

    public $uniqueFor = 3600;

    public function __construct(public SourceFile $sourceFile, public Agent $agent)
    {
        if ($sourceFile->isWordFile()) {
            $this->onQueue('ingestion.word');
        } elseif ($sourceFile->isPdfFile()) {
            $this->onQueue('ingestion.pdf');
        }
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
        $jobId = $this->job->getJobId();

        Log::error('IngestionJob failed', [
            'source_file_id' => $this->sourceFile->id,
            'job_id' => $jobId,
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
        $jobId = $this->job?->getJobId() ?? $this->job?->uuid() ?? 'unknown';

        Log::warning('IngestionJob timed out', [
            'source_file_id' => $this->sourceFile->id,
            'job_id' => $jobId,
        ]);

        $this->sourceFile->update([
            'status' => 'failed',
            'failure_reason' => 'Job timed out after '.$this->timeout.' seconds',
        ]);
    }
}
