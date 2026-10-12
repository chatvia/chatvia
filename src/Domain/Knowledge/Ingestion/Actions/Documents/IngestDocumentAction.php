<?php

namespace Domain\Knowledge\Ingestion\Actions\Documents;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Ingest a PDF, office file or image: read it into pages with the configured
 * DocumentParser, then chunk and embed the pages in one batch.
 *
 * Parsing is the expensive step, so it is done once: the pages are cached by
 * the file's checksum (the same file uploaded twice is parsed once) and stored
 * as JSON in `parser_filepath`, so a retry reads them back without parsing.
 */
class IngestDocumentAction
{
    public function __construct(
        private readonly DocumentParser $parser,
        private readonly DispatchSourceIngestionBatchAction $dispatchBatch,
    ) {}

    public function execute(SourceFile $sourceFile, Agent $agent, ?string $internalJobId = null): void
    {
        if ($sourceFile->isIngested()) {
            return;
        }

        $sourceFile->update([
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => $this->parser->name(),
        ]);

        $pages = $this->pages($sourceFile, $agent);

        $this->dispatchBatch->execute(
            $sourceFile,
            $agent,
            DocumentChunkIngestionJob::forPages($agent, $sourceFile, $pages),
        );
    }

    /**
     * @return Collection<int, PageData>
     */
    private function pages(SourceFile $sourceFile, Agent $agent): Collection
    {
        if ($sourceFile->parser_filepath) {
            return PageData::fromJson($sourceFile->readContents('parser_filepath'));
        }

        $agentDirectory = KnowledgeStoragePath::forAgent($agent);

        [$checksum, $pages] = $sourceFile->withLocalFile(function (File $file) use ($agentDirectory): array {
            $checksum = (string) md5_file($file->getPathname());

            $pages = Cache::remember(
                'files:ocr:'.$checksum,
                now()->addMonth(),
                fn (): Collection => $this->parser->parse($file->getPathname(), $agentDirectory.'/tmp'),
            );

            return [$checksum, $pages];
        });

        $pagesPath = $agentDirectory.'/'.$checksum.'.json';
        Storage::put($pagesPath, $pages->toJson());

        $sourceFile->update(['parser_filepath' => $pagesPath]);

        return $pages;
    }
}
