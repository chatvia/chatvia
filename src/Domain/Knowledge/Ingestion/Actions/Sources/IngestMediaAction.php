<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Ingestion\Services\TokenSplitter;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Operations\Media\Transcription;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class IngestMediaAction
{
    public function __construct(
        private readonly AudioCompressor $audioCompressor,
        private readonly TokenSplitter $splitter,
        private readonly Transcription $transcription,
    ) {}

    public function execute(SourceFile $sourceFile, Agent $agent, ?string $internalJobId = null): void
    {
        if ($sourceFile->status === SourceStatus::Completed->value) {
            return;
        }

        $startTime = microtime(true);

        $sourceFile->update([
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => 'whisper',
            'status' => SourceStatus::Processing->value,
        ]);

        if ($sourceFile->parser_filepath) {
            $file = $sourceFile->readFile();
            $transcription = $file->getContent();
        } else {
            $file = $sourceFile->readFile();

            $transcription = Cache::remember('ingestion:media:transcription:file:'.$sourceFile->checksum, now()->addMonth(), function () use ($file) {
                $compressedFilePath = $this->audioCompressor->process($file->getPathname());

                return $this->transcription->transcribe($compressedFilePath);
            });

            $filePath = 'agents/'.$agent->id.'/audio/'.Str::uuid()->toString().'.txt';

            Storage::put($filePath, $transcription);

            $sourceFile->update(['parser_filepath' => $filePath]);
        }

        if (empty($transcription)) {
            throw new RuntimeException('No valid pages data available for chunking');
        }

        $sourceFile->refresh();

        dispatch(new SummaryIngestionJob($sourceFile));

        $texts = collect($this->splitter->split($transcription));

        $jobs = collect($texts)->map(fn (PageData $page): MediaChunkIngestionJob => new MediaChunkIngestionJob($agent, $sourceFile, $page));

        $sourceFileId = $sourceFile->id;
        $agentExternalId = $agent->external_id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$sourceFileId)
            // A single failed chunk job must not cancel the rest of the
            // transcript; finally() keeps the terminal status + broadcast
            // unconditional (see IngestDocumentAction for the rationale).
            ->allowFailures()
            ->finally(function (Batch $batch) use ($sourceFileId, $agentExternalId): void {
                $file = SourceFile::find($sourceFileId);

                if (! $file) {
                    return;
                }

                $file->update(['status' => SourceStatus::Completed->value]);

                FileIngestionProgressed::dispatch($file, $agentExternalId);
            })
            ->dispatch();

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        $sourceFile->update([
            'expected_chunks' => count($texts),
            'internal_ingestion_job_id' => $batch->id,
            'took_in_seconds' => $tookInSeconds,
        ]);
    }
}
