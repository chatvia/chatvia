<?php

namespace Domain\Ingestion\Actions\Sources;

use App\LLM\Operations\Media\Transcription;
use App\Media\AudioCompressor;
use Domain\Agent\Models\Agent;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Domain\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Ingestion\Services\TokenSplitter;
use Domain\Source\File\Models\SourceFile;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        if ($sourceFile->status === 'ingested') {
            return;
        }

        $startTime = microtime(true);

        $sourceFile->update([
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => 'whisper',
            'status' => 'ingesting',
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

        $agentFileId = $agent->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agent->id)
            ->then(function (Batch $batch) use ($agentFileId): void {
                $file = SourceFile::find($agentFileId);
                $file?->update(['status' => 'ingested']);

                DB::statement('ANALYZE chunks');
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
