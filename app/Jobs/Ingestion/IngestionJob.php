<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\ChunkIngestionData;
use App\Models\AgentFile;
use App\Services\Chunker;
use App\Services\Ingestion\LlamaParse;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

class IngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 10;

    public $timeout = 1800;

    public $uniqueFor = 3600;

    public function __construct(public AgentFile $agentFile)
    {
        $this->onQueue('ingestion');
    }

    public function handle(LlamaParse $llamaParse): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->agentFile->status === 'completed') {
            return;
        }

        $externalJobId = $this->agentFile->external_parsing_job_id;

        $internalJobId = $this->job->getJobId();

        $updateValues = [
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => 'llamaparse',
        ];

        // Get the file once and reuse it
        $file = $this->agentFile->isWordFile() ? $this->agentFile->convertToPdf() : $this->agentFile->readFile();

        if (! $externalJobId) {
            $externalJobId = $llamaParse->parse($file);
            $updateValues['external_parsing_job_id'] = $externalJobId;
        }

        if ($this->agentFile->parser_filepath) {
            $pages = Storage::get($this->agentFile->parser_filepath);
            $pages = json_decode($pages, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $pages = $llamaParse->getPagesWithImages($externalJobId);

            $fileName = Str::uuid()->toString().'.json';

            $updateValues['parser_filepath'] = Storage::putFileAs(
                path: 'users/'.$this->agentFile->user_id.'/agents/'.$this->agentFile->id,
                file: new File(create_temp_file($fileName, json_encode($pages, JSON_THROW_ON_ERROR))),
                name: 'parser.json',
            );
        }

        $this->agentFile->update($updateValues);

        $this->agentFile->refresh();

        // Validate pages data before chunking
        if (! is_array($pages) || empty($pages)) {
            throw new RuntimeException('No valid pages data available for chunking');
        }

        $chunker = new Chunker($this->agentFile);
        $chunks = $chunker->createChunks($pages);

        $jobs = $chunks->map(function (ChunkIngestionData $chunk) {
            return new IngestionChunkJob($chunk);
        });

        $parsedContent = Storage::get($this->agentFile->parser_filepath);

        $content = collect(json_decode($parsedContent, true, 512, JSON_THROW_ON_ERROR))
            ->pluck('text')
            ->join("\n");

        dispatch(new IngestionSummaryJob($this->agentFile, $content));

        $agentFileId = $this->agentFile->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agentFileId)
            ->then(function (Batch $batch) use ($agentFileId) {
                AgentFile::find($agentFileId)?->update(['status' => 'completed']);
            })
            ->dispatch();

        // Use transaction to ensure atomic status update
        DB::transaction(function () use ($chunks, $batch) {
            $this->agentFile->update([
                'status' => 'processing',
                'internal_chunking_batch_id' => $batch->id,
                'chunks' => $chunks->count(),
            ]);
        });
    }

    public function uniqueId(): string
    {
        return 'ingestion_'.$this->agentFile->id;
    }

    public function uniqueFor(): int
    {
        return $this->uniqueFor;
    }

    public function tags(): array
    {
        return [
            'agent_file:'.$this->agentFile->id,
            'ingestion_job',
        ];
    }

    public function failed(Throwable $exception): void
    {
        // Get the current job ID for logging
        $jobId = $this->job->getJobId();

        Log::error('IngestionJob failed', [
            'agent_file_id' => $this->agentFile->id,
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
            'agent_file_id' => $this->agentFile->id,
            'job_id' => $jobId,
        ]);

        $this->agentFile->update([
            'status' => 'failed',
            'failure_reason' => 'Job timed out after '.$this->timeout.' seconds',
        ]);
    }
}
