<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\ChunkIngestionData;
use App\Models\SourceFile;
use App\Services\Chunking\Chunker;
use App\Services\Ingestion\Clients\LlamaParse;
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
use RuntimeException;
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

    public function __construct(public SourceFile $sourceFile, public int $agentId)
    {
        if ($sourceFile->isWordFile()) {
            $this->onQueue('ingestion.word');
        } elseif ($sourceFile->isPdfFile()) {
            $this->onQueue('ingestion.pdf');
        }
    }

    public function handle(LlamaParse $llamaParse): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->sourceFile->status === 'ingested') {
            return;
        }

        $externalJobId = $this->sourceFile->external_parsing_job_id;

        $internalJobId = $this->job->getJobId();

        $updateValues = [
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => $this->sourceFile->isAudioFile() ? 'whisper' : 'llamaparse',
        ];

        // Get the file once and reuse it
        $file = $this->sourceFile->isWordFile() ? $this->sourceFile->convertToPdf() : $this->sourceFile->readFile();

        // Calculate file checksum for caching
        $fileChecksum = md5_file($file->getPathname());
        $cacheKey = "llamaparse:file:{$fileChecksum}";

        if (! $externalJobId) {
            $externalJobId = $llamaParse->parse($file);
            $updateValues['external_parsing_job_id'] = $externalJobId;
        }

        if ($this->sourceFile->parser_filepath) {
            $pages = Storage::get($this->sourceFile->parser_filepath);
            $pages = json_decode($pages, true, 512, JSON_THROW_ON_ERROR);
        } else {
            // Check cache first (24 hours)
            $pages = Cache::remember($cacheKey, 86400, function () use ($llamaParse, $externalJobId) {
                return $llamaParse->getPagesWithImages($externalJobId);
            });

            $fileName = Str::uuid()->toString().'.json';

            $updateValues['parser_filepath'] = Storage::putFileAs(
                path: 'users/'.$this->sourceFile->user_id.'/agents/'.$this->sourceFile->id,
                file: new File(create_temp_file($fileName, json_encode($pages, JSON_THROW_ON_ERROR))),
                name: 'parser.json',
            );
        }

        $this->sourceFile->update($updateValues);

        $this->sourceFile->refresh();

        // Validate pages data before chunking
        if (! is_array($pages) || empty($pages)) {
            throw new RuntimeException('No valid pages data available for chunking');
        }

        $chunker = new Chunker(
            agentId: $this->agentId,
            chunkableType: SourceFile::class,
            chunkableId: $this->sourceFile->id,
        );
        $chunks = $chunker->createChunks($pages);

        $parsedContent = Storage::get($this->sourceFile->parser_filepath);

        $content = collect(json_decode($parsedContent, true, 512, JSON_THROW_ON_ERROR))
            ->pluck('text')
            ->join("\n");

        $agentFileId = $this->sourceFile->id;

        $jobs = $chunks->map(function (ChunkIngestionData $chunk) {
            return new ChunkIngestionJob($chunk);
        });

//        SummaryIngestionJob::dispatch($this->sourceFile, $content);

        // Set expected_chunks BEFORE dispatching batch jobs to ensure progress calculation works
        $this->sourceFile->update([
            'status' => 'ingesting',
            'expected_chunks' => $chunks->count(),
        ]);

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agentFileId)
            ->then(function (Batch $batch) use ($agentFileId) {
                $file = SourceFile::find($agentFileId);
                $file?->update(['status' => 'ingested']);
            })
            ->dispatch();

        // Update batch ID after dispatch
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
