<?php

namespace App\Jobs\Ingestion\Document;

use App\Contracts\DocumentOCRInterface;
use App\Data\Ingestion\OCR\PageData;
use App\Models\Agent;
use App\Models\SourceFile;
use App\Services\Infrastructure\MistralFiles;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;
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

    public function handle(DocumentOCRInterface $documentOCR, MistralFiles $mistralFiles): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->sourceFile->isIngested()) {
            return;
        }

        $internalJobId = $this->job->getJobId();

        $updateValues = [
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => 'mistral-ocr',
        ];

        // Get the file once and reuse it
        $file = $this->sourceFile->readFile();
        $filePath = $file->getPathname();

        // Calculate file checksum for caching
        $fileChecksum = md5_file($filePath);

        if ($this->sourceFile->parser_filepath) {
            $pages = Storage::get($this->sourceFile->parser_filepath);
            $pages = PageData::fromJson($pages);
        } else {
            $pages = Cache::remember('files:ocr:'.$fileChecksum, now()->addMonth(), function () use ($documentOCR, $filePath) {
                return $documentOCR->fromFile($filePath);
            });

            $fileName = Str::uuid()->toString().'.json';

            $updateValues['parser_filepath'] = Storage::putFileAs(
                path: 'users/'.$this->sourceFile->user_id.'/agents/'.$this->agent->id,
                file: new File(create_temp_file($fileName, $pages->toJson())),
                name: $fileChecksum.'.json',
            );
        }

        $this->sourceFile->update($updateValues);

        $this->sourceFile->refresh();

        $jobs = $pages->map(function (PageData $page, int $index) use ($pages) {
            $previousPage = $index > 0 ? $pages[$index - 1] : null;
            $nextPage = $index < $pages->count() - 1 ? $pages[$index + 1] : null;

            return new DocumentChunkIngestionJob(
                $this->agent,
                $this->sourceFile,
                $page,
                $previousPage,
                $nextPage
            );
        });

        // Upload file to Mistral for summary generation
        //        $mistralFile = $mistralFiles->upload($filePath);

        //        SummaryIngestionJob::dispatch(
        //            $this->sourceFile,
        //            $mistralFile,
        //        );

        $this->sourceFile->update([
            'status' => 'ingesting',
            'expected_chunks' => $pages->count(),
        ]);

        $agentFileId = $this->sourceFile->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agentFileId)
            ->then(function (Batch $batch) use ($agentFileId) {
                $file = SourceFile::find($agentFileId);
                $file?->update(['status' => 'ingested']);
            })
            ->dispatch();

        $this->sourceFile->update([
            'internal_chunking_batch_id' => $batch->id,
        ]);
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
        // Get the current job ID for logging
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
        // Get the current job ID for logging
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
