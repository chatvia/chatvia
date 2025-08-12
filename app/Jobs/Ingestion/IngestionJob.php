<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\ChunkIngestionData;
use App\Models\AgentFile;
use App\Services\Chunker;
use App\Services\Ingestion\LlamaParse;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class IngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 10;

    public $timeout = 1800;

    public function __construct(public AgentFile $agentFile)
    {
        $this->onQueue('ingestion');
    }

    /**
     * @throws Throwable
     * @throws \JsonException
     */
    public function handle(LlamaParse $llamaParse): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $externalJobId = $this->agentFile->external_parsing_job_id;

        $internalJobId = $this->job->getJobId();

        $updateValues = [
            'internal_ingestion_job_id' => $internalJobId ?: null,
            'parser' => 'llamaparse',
        ];

        // Get the file once and reuse it
        $file = $this->agentFile->isWordFile() ? $this->agentFile->convertToPdf() : $this->agentFile->readFile();

        if (! $externalJobId) {
            $externalJobId = $llamaParse->parse($file);
            $updateValues['external_parsing_job_id'] = $externalJobId;
        }

        $cacheKey = 'agent:file:parsing:'.md5_file($file->getPathname());

        if (Cache::has($cacheKey)) {
            $pages = Cache::get($cacheKey);
        } else {
            $pages = $llamaParse->getPagesWithImages($externalJobId);

            Cache::put($cacheKey, $pages, now()->addMonth());

            $fileName = Str::uuid()->toString().'.json';

            $updateValues['parser_filepath'] = Storage::putFileAs(
                path: 'users/'.$this->agentFile->user_id.'/agents/'.$this->agentFile->id,
                file: new File(create_temp_file($fileName, json_encode($pages, JSON_THROW_ON_ERROR))),
                name: 'parser.json',
            );
        }

        $this->agentFile->update($updateValues);

        $this->agentFile->refresh();

        $chunker = new Chunker($this->agentFile);
        $chunks = $chunker->createChunks($pages);

        $jobs = $chunks->map(function (ChunkIngestionData $chunk) {
            return new IngestionChunkJob($chunk);
        });

        IngestionSummaryJob::dispatch($this->agentFile);

        $agentFileId = $this->agentFile->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agentFileId)
            ->then(function (Batch $batch) use ($agentFileId) {
                AgentFile::find($agentFileId)?->update(['status' => 'completed']);
            })
            ->dispatch();

        $this->agentFile->update([
            'status' => 'processing',
            'internal_chunking_batch_id' => $batch->id,
            'chunks' => $chunks->count(),
        ]);
    }

    public function uniqueId(): string
    {
        return $this->agentFile->id;
    }

    public function tags(): array
    {
        return [
            'agent_file:'.$this->agentFile->id,
        ];
    }

    public function failed(Throwable $exception): void
    {
        $this->agentFile->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
        ]);
    }
}
