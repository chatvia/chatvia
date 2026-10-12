<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Ingestion\Services\TokenSplitter;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Llm\Contracts\Transcriber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Ingest an audio or video file: compress the audio, transcribe it with the
 * configured Transcriber, then split the transcript and chunk the parts in one
 * batch.
 *
 * Transcription is the slow and paid step, so it is done once: the transcript
 * is cached by the file's checksum and stored in `parser_filepath`, and a retry
 * chunks the stored transcript without downloading the audio again.
 */
class IngestMediaAction
{
    public function __construct(
        private readonly AudioCompressor $audioCompressor,
        private readonly TokenSplitter $splitter,
        private readonly Transcriber $transcriber,
        private readonly DispatchSourceIngestionBatchAction $dispatchBatch,
    ) {}

    public function execute(SourceFile $sourceFile, Agent $agent, ?string $internalJobId = null): void
    {
        if ($sourceFile->isIngested()) {
            return;
        }

        $startTime = microtime(true);

        $sourceFile->update([
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => 'whisper',
            'status' => SourceStatus::Processing,
        ]);

        $transcript = $this->transcript($sourceFile, $agent);

        if (trim($transcript) === '') {
            throw new RuntimeException('The transcript is empty: there is nothing to chunk.');
        }

        $jobs = MediaChunkIngestionJob::forPages($agent, $sourceFile, collect($this->splitter->split($transcript)));

        $this->dispatchBatch->execute($sourceFile, $agent, $jobs);

        $sourceFile->update(['took_in_seconds' => (int) ceil(microtime(true) - $startTime)]);
    }

    /**
     * The stored transcript on a retry; otherwise download, compress and
     * transcribe the audio (cached by checksum) and store the transcript.
     */
    private function transcript(SourceFile $sourceFile, Agent $agent): string
    {
        if ($sourceFile->parser_filepath) {
            return $sourceFile->readContents('parser_filepath');
        }

        $transcript = Cache::remember(
            'ingestion:media:transcription:file:'.$sourceFile->checksum,
            now()->addMonth(),
            fn (): string => $sourceFile->withLocalFile(
                fn (File $file): string => $this->transcriber->transcribe($this->audioCompressor->process($file->getPathname())),
            ),
        );

        $path = KnowledgeStoragePath::forAgent($agent, 'audio').'/'.Str::uuid()->toString().'.txt';
        Storage::put($path, $transcript);

        $sourceFile->update(['parser_filepath' => $path]);

        return $transcript;
    }
}
