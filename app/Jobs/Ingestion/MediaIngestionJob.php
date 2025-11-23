<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\ChunkIngestionData;
use App\Models\SourceFile;
use App\Services\Chunking\Chunker;
use App\Services\Infrastructure\LLM\OpenAI;
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

    public function __construct(public SourceFile $agentFile, public int $agentId)
    {
        // Assign to appropriate queue based on file type
        if ($agentFile->isVideoFile()) {
            $this->onQueue('ingestion.video');
        } elseif ($agentFile->isAudioFile()) {
            $this->onQueue('ingestion.audio');
        }
    }

    public function handle(AudioCompressor $audioCompressor): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->agentFile->status === 'ingested') {
            return;
        }

        $updateValues = [
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'parser' => 'whisper',
        ];

        if ($this->agentFile->parser_filepath) {
            $file = $this->agentFile->readFile();
            $transcription = $file->getContent();
        } else {
            $file = $this->agentFile->readFile();
            $compressedFilePath = $audioCompressor->process($file->getPathname());
            $transcription = OpenAI::transcribe($compressedFilePath);
        }

        if (empty($transcription)) {
            throw new RuntimeException('No valid pages data available for chunking');
        }

        $chunker = new Chunker(
            agentId: $this->agentId,
            chunkableType: SourceFile::class,
            chunkableId: $this->agentFile->id,
        );

        $pages = [
            ['text' => $transcription],
        ];

        $chunks = $chunker->createChunks($pages);

        $updateValues['expected_chunks'] = $chunks->count();

        $this->agentFile->update($updateValues);

        $this->agentFile->refresh();

        dispatch(new SummaryIngestionJob($this->agentFile, $transcription));

        $agentFileId = $this->agentFile->id;

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
                        \App\Events\FileIngestionProgressEvent::dispatch($file, $agent->id);
                    }
                }
            })
            ->dispatch();

        // Update batch ID after dispatch
        $this->agentFile->update([
            'internal_chunking_batch_id' => $batch->id,
        ]);
    }

    public function uniqueId(): string
    {
        return 'ingestion:media:'.$this->agentFile->id;
    }

    public function uniqueFor(): int
    {
        return $this->uniqueFor;
    }

    public function tags(): array
    {
        return [
            'source_file:'.$this->agentFile->id,
            'ingestion_job',
            'media',
        ];
    }

    public function failed(Throwable $exception): void
    {
        // Get the current job ID for logging
        $jobId = $this->job->getJobId();

        Log::error('IngestionJob failed', [
            'source_file_id' => $this->agentFile->id,
            'job_id' => $jobId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->agentFile->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
        ]);
    }

    public function timeout(): void
    {
        // Get the current job ID for logging
        $jobId = $this->job?->getJobId() ?? $this->job?->uuid() ?? 'unknown';

        Log::warning('IngestionJob timed out', [
            'source_file_id' => $this->agentFile->id,
            'job_id' => $jobId,
        ]);

        $this->agentFile->update([
            'status' => 'failed',
            'failure_reason' => 'Job timed out after '.$this->timeout.' seconds',
        ]);
    }
}
