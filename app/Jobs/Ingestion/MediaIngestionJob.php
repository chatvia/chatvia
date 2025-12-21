<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\ChunkIngestionData;
use App\Models\Agent;
use App\Models\SourceFile;
use App\Services\Chunking\Chunker;
use App\Services\Infrastructure\LLM\Operations\Transcription;
use App\Services\Media\AudioCompressor;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public $tries = 3;

    public $backoff = 10;

    public $timeout = 1800;

    public $uniqueFor = 3600;

    public function __construct(public SourceFile $sourceFile, public Agent $agent)
    {
        // Assign to appropriate queue based on file type
        if ($sourceFile->isVideoFile()) {
            $this->onQueue('ingestion.video');
        } elseif ($sourceFile->isAudioFile()) {
            $this->onQueue('ingestion.audio');
        }
    }

    public function handle(AudioCompressor $audioCompressor): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->sourceFile->status === 'ingested') {
            return;
        }

        $updateValues = [
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'parser' => 'whisper',
        ];

        if ($this->sourceFile->parser_filepath) {
            $file = $this->sourceFile->readFile();
            $transcription = $file->getContent();
        } else {
            $file = $this->sourceFile->readFile();
            $compressedFilePath = $audioCompressor->process($file->getPathname());
            $transcription = app(Transcription::class)->transcribe($compressedFilePath);
        }

        if (empty($transcription)) {
            throw new RuntimeException('No valid pages data available for chunking');
        }

        $chunker = new Chunker(
            agent: $this->agent->id,
            chunkable: SourceFile::class,
            chunkableId: $this->sourceFile->id,
        );

        $pages = [
            ['text' => $transcription],
        ];

        $chunks = $chunker->createChunks($pages);

        $updateValues['expected_chunks'] = $chunks->count();

        $this->sourceFile->update($updateValues);

        $this->sourceFile->refresh();

        $filePath = 'users/'.$this->agent->user_id.'/agent/'.$this->agent->id.'/'.Str::uuid()->toString().'.txt';
        // TODO: fix it
        dispatch(new SummaryIngestionJob($this->sourceFile, $transcription));

        $agentFileId = $this->sourceFile->id;

        $jobs = $chunks->map(function (ChunkIngestionData $chunk) {
            return new ChunkIngestionJob($chunk);
        });

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agentFileId)
            ->then(function (Batch $batch) use ($agentFileId) {
                $file = SourceFile::find($agentFileId);
                $file?->update(['status' => 'ingested']);

                // Dispatch final progress event for all agents using this file
                if ($file) {
                    foreach ($file->agents as $agent) {
                        \App\Events\FileIngestionProgressEvent::dispatch($file, $agent->external_id);
                    }
                }
            })
            ->dispatch();

        // Update batch ID after dispatch
        $this->sourceFile->update([
            'internal_chunking_batch_id' => $batch->id,
        ]);
    }

    public function uniqueId(): string
    {
        return 'ingestion:media:'.$this->sourceFile->id;
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
