<?php

namespace App\Jobs\Ingestion\Media;

use App\Data\Ingestion\ChunkIngestionData;
use App\Data\Ingestion\OCR\PageData;
use App\Events\FileIngestionProgressEvent;
use App\Jobs\Ingestion\ChunkIngestionJob;
use App\Jobs\Ingestion\SummaryIngestionJob;
use App\Models\Agent;
use App\Models\SourceFile;
use App\Services\Chunking\Chunker;
use App\Services\Infrastructure\LLM\Operations\Transcription;
use App\Services\Media\AudioCompressor;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
use App\Services\TikToken;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
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

    public function handle(AudioCompressor $audioCompressor, RecursiveCharacterTextSplitter $splitter): void
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

            $filePath = 'agents/'.$this->agent->id.'/audio/'.Str::uuid()->toString().'.txt';

            Storage::put($filePath, $transcription);

            $this->sourceFile->update([
                'parser_filepath' => $filePath,
            ]);
        }

        $this->sourceFile->refresh();

        if (empty($transcription)) {
            throw new RuntimeException('No valid pages data available for chunking');
        }

        // Split transcription if it's too large
        if (TikToken::countTokens($transcription) <= 1000) {
            $pages = collect([
                new PageData(pageNumber: 0, content: $transcription),
            ]);
        } else {
            $pages = collect($splitter->splitText($transcription))
                ->map(function ($text) {
                    return new PageData(
                        pageNumber: 0,
                        content: $text,
                    );
                });
        }

        $chunker = new Chunker(
            agent: $this->agent,
            chunkable: $this->sourceFile,
        );

        $chunks = $chunker->createChunks($pages);

        $updateValues['expected_chunks'] = $chunks->count();

        $this->sourceFile->update($updateValues);

        $this->sourceFile->refresh();

        dispatch(
            new SummaryIngestionJob(
                $this->sourceFile,
                route('external.source.files.view', [
                    'type' => 'file',
                    'externalId' => $this->sourceFile->external_id,
                ]),
            )
        );

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
                        FileIngestionProgressEvent::dispatch($file, $agent->external_id);
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
