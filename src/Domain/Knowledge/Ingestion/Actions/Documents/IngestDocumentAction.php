<?php

namespace Domain\Knowledge\Ingestion\Actions\Documents;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Services\Clients\MistralOCR;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class IngestDocumentAction
{
    public function __construct(
        private readonly MistralOCR $documentOCR,
    ) {}

    public function execute(SourceFile $sourceFile, Agent $agent, ?string $internalJobId = null): void
    {
        if ($sourceFile->isIngested()) {
            return;
        }

        $updateValues = [
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => 'mistral-ocr',
        ];

        // Get the file once and reuse it; the local copy is deleted below.
        $file = $sourceFile->readFile();
        $filePath = $file->getPathname();

        try {
            // Calculate file checksum for caching
            $fileChecksum = md5_file($filePath);

            if ($sourceFile->parser_filepath) {
                $pages = Storage::get($sourceFile->parser_filepath);
                $pages = PageData::fromJson($pages);
            } else {
                $agentStoragePath = 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id;

                $pages = Cache::remember('files:ocr:'.$fileChecksum, now()->addMonth(), function () use ($filePath, $agentStoragePath) {
                    return $this->documentOCR->fromFile($filePath, $agentStoragePath.'/tmp');
                });

                $fileName = Str::uuid()->toString().'.json';
                $pagesJsonPath = create_temp_file($fileName, $pages->toJson());

                try {
                    $updateValues['parser_filepath'] = Storage::putFileAs(
                        path: $agentStoragePath,
                        file: new File($pagesJsonPath),
                        name: $fileChecksum.'.json',
                    );
                } finally {
                    @unlink($pagesJsonPath);
                }
            }
        } finally {
            @unlink($filePath);
        }

        $sourceFile->update($updateValues);

        $sourceFile->refresh();

        $jobs = $pages->map(function (PageData $page, int $index) use ($pages, $agent, $sourceFile) {
            $previousPage = $index > 0 ? $pages[$index - 1] : null;
            $nextPage = $index < $pages->count() - 1 ? $pages[$index + 1] : null;

            return new DocumentChunkIngestionJob(
                $agent,
                $sourceFile,
                $page,
                $previousPage,
                $nextPage
            );
        });

        SummaryIngestionJob::dispatch($sourceFile);

        $sourceFile->update([
            'status' => SourceStatus::Processing->value,
            'expected_chunks' => $pages->count(),
        ]);

        $agentFileId = $sourceFile->id;
        $agentExternalId = $agent->external_id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agentFileId)
            ->allowFailures() // one failed page must not cancel the batch
            ->finally(function (Batch $batch) use ($agentFileId, $agentExternalId): void {
                $file = SourceFile::find($agentFileId);

                if (! $file) {
                    return;
                }

                $file->update(['status' => SourceStatus::Completed->value]);

                // Authoritative "ingestion finished" signal. The per-chunk
                // progress events can never reach 100% on their own — deduped or
                // blank pages create no chunk, so importedChunks stays below
                // expected_chunks — which leaves the client stuck on "ingesting".
                // Broadcasting here (finally, so it runs even when stray page
                // jobs failed), where ingestionProgress() returns 100 via the
                // Completed-status short-circuit, guarantees the UI flips to
                // "ingested" regardless of dedup, races, or a dropped frame.
                FileIngestionProgressed::dispatch($file, $agentExternalId);
            })
            ->dispatch();

        $sourceFile->update([
            'internal_chunking_batch_id' => $batch->id,
        ]);
    }
}
