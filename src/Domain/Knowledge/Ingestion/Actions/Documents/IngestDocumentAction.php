<?php

namespace Domain\Knowledge\Ingestion\Actions\Documents;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Contracts\DocumentOCRInterface;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class IngestDocumentAction
{
    public function __construct(
        private readonly DocumentOCRInterface $documentOCR,
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

        // Get the file once and reuse it
        $file = $sourceFile->readFile();
        $filePath = $file->getPathname();

        // Calculate file checksum for caching
        $fileChecksum = md5_file($filePath);

        if ($sourceFile->parser_filepath) {
            $pages = Storage::get($sourceFile->parser_filepath);
            $pages = PageData::fromJson($pages);
        } else {
            $pages = Cache::remember('files:ocr:'.$fileChecksum, now()->addMonth(), function () use ($filePath) {
                return $this->documentOCR->fromFile($filePath);
            });

            $fileName = Str::uuid()->toString().'.json';

            $updateValues['parser_filepath'] = Storage::putFileAs(
                path: 'users/'.$sourceFile->user_id.'/agents/'.$agent->id,
                file: new File(create_temp_file($fileName, $pages->toJson())),
                name: $fileChecksum.'.json',
            );
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
            'status' => 'ingesting',
            'expected_chunks' => $pages->count(),
        ]);

        $agentFileId = $sourceFile->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent File ID: '.$agentFileId)
            ->then(function (Batch $batch) use ($agentFileId): void {
                $file = SourceFile::find($agentFileId);
                $file?->update(['status' => 'ingested']);

                // Refresh planner stats so vchordrq prefilter sees the new
                // reltuples — autovacuum's default schedule waits far too long.
                DB::statement('ANALYZE chunks');
            })
            ->dispatch();

        $sourceFile->update([
            'internal_chunking_batch_id' => $batch->id,
        ]);
    }
}
