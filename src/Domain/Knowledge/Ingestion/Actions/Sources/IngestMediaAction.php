<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaChunkIngestionJob;
use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Llm\Contracts\Transcriber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Ingest an audio or video file: compress the audio, transcribe it with the
 * configured Transcriber, then split the transcript with the text splitter
 * (whole sentences by default) and chunk the parts in one batch.
 *
 * Transcription is the slow and paid step, so it is done once: the transcript
 * is cached by the file's checksum and stored in `parser_filepath`, and a retry
 * chunks the stored transcript without downloading the audio again.
 */
class IngestMediaAction
{
    public function __construct(
        private readonly AudioCompressor $audioCompressor,
        private readonly TextSplitter $splitter,
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
            'parser' => $this->transcriber->name(),
            'status' => SourceStatus::Processing,
        ]);

        $transcript = $this->transcript($sourceFile);

        if (trim($transcript) === '') {
            throw new RuntimeException('The transcript is empty: there is nothing to chunk.');
        }

        $parts = $this->splitter->split($transcript, ContentKind::Transcript);

        $jobs = MediaChunkIngestionJob::forPages($agent, $sourceFile, collect(PageData::fromParts($parts)));

        $this->dispatchBatch->execute($sourceFile, $agent, $jobs);

        $sourceFile->update(['took_in_seconds' => (int) ceil(microtime(true) - $startTime)]);
    }

    /**
     * The stored transcript on a retry; otherwise download, compress and
     * transcribe the audio (cached by checksum) and store the transcript in
     * the source's own folder.
     */
    private function transcript(SourceFile $sourceFile): string
    {
        if ($sourceFile->parser_filepath) {
            return $sourceFile->readContents('parser_filepath');
        }

        // Keyed by the model too: another speech-to-text model transcribes again.
        $transcript = Cache::remember(
            'ingestion:media:transcription:'.md5($this->transcriber->name()).':file:'.$sourceFile->checksum,
            now()->addMonth(),
            fn (): string => $sourceFile->withLocalFile(
                fn (File $file): string => $this->transcriber->transcribe($this->audioCompressor->process($file->getPathname())),
            ),
        );

        $path = KnowledgeStoragePath::forSource($sourceFile).'/transcript.txt';
        Storage::put($path, $transcript);

        $sourceFile->update(['parser_filepath' => $path]);

        return $transcript;
    }
}
