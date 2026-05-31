<?php

namespace Domain\Ingestion\Jobs\Media;

use App\LLM\Operations\Transcription;
use App\Media\AudioCompressor;
use Domain\Agents\Models\Agent;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Ingestion\Services\TokenSplitter;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public $tries = 2;

    public $timeout = 86000;

    public function __construct(public SourceFile $sourceFile, public Agent $agent)
    {
        // Assign to appropriate queue based on file type
        if ($sourceFile->isVideoFile()) {
            $this->onQueue('ingestion.video');
        } elseif ($sourceFile->isAudioFile()) {
            $this->onQueue('ingestion.audio');
        }
    }

    public function handle(AudioCompressor $audioCompressor, TokenSplitter $splitter): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->sourceFile->status === 'ingested') {
            return;
        }

        $startTime = microtime(true);

        $updateValues = [
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'parser' => 'whisper',
            'status' => 'ingesting',
        ];

        $this->sourceFile->update($updateValues);

        if ($this->sourceFile->parser_filepath) {
            $file = $this->sourceFile->readFile();
            $transcription = $file->getContent();
        } else {
            $file = $this->sourceFile->readFile();

            $transcription = Cache::remember('ingestion:media:transcription:file:'.$this->sourceFile->checksum, now()->addMonth(), function () use ($audioCompressor, $file) {
                $compressedFilePath = $audioCompressor->process($file->getPathname());

                return app(Transcription::class)->transcribe($compressedFilePath);
            });

            $filePath = 'agents/'.$this->agent->id.'/audio/'.Str::uuid()->toString().'.txt';

            Storage::put($filePath, $transcription);

            $this->sourceFile->update(['parser_filepath' => $filePath]);
        }

        if (empty($transcription)) {
            throw new RuntimeException('No valid pages data available for chunking');
        }

        $this->sourceFile->refresh();

        dispatch(new SummaryIngestionJob($this->sourceFile));

        $texts = collect($splitter->split($transcription));

        $jobs = collect($texts)->map(fn (PageData $page): MediaChunkIngestionJob => new MediaChunkIngestionJob($this->agent, $this->sourceFile, $page));

        $agentFileId = $this->agent->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$this->agent->id)
            ->then(function (Batch $batch) use ($agentFileId): void {
                $file = SourceFile::find($agentFileId);
                $file?->update(['status' => 'ingested']);

                DB::statement('ANALYZE chunks');
            })
            ->dispatch();

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        $this->sourceFile->update([
            'expected_chunks' => count($texts),
            'internal_ingestion_job_id' => $batch->id,
            'took_in_seconds' => $tookInSeconds,
        ]);
    }

    public function uniqueId(): string
    {
        return 'ingestion:media:'.$this->sourceFile->id;
    }

    public function tags(): array
    {
        return [
            'source_file:'.$this->sourceFile->id,
            'ingestion_job',
            'media',
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
