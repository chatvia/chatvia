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
 * Both the pages file and any short-lived copy the parser uploads go in the
 * source's own storage folder ({@see KnowledgeStoragePath}).
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

        $pages = $this->pages($sourceFile);

        $this->dispatchBatch->execute(
            $sourceFile,
            $agent,
            DocumentChunkIngestionJob::forPages($agent, $sourceFile, $pages),
        );
    }

    /**
     * @return Collection<int, PageData>
     */
    private function pages(SourceFile $sourceFile): Collection
    {
        if ($sourceFile->parser_filepath) {
            return PageData::fromJson($sourceFile->readContents('parser_filepath'));
        }

        $tmpDirectory = KnowledgeStoragePath::forSource($sourceFile, 'tmp');

        // Keyed by the reader too: a file read by another reader or model is read again.
        $pages = $sourceFile->withLocalFile(fn (File $file): Collection => Cache::remember(
            'files:ocr:'.md5($this->parser->name()).':'.md5_file($file->getPathname()),
            now()->addMonth(),
            fn (): Collection => $this->parser->parse($file->getPathname(), $tmpDirectory),
        ));

        $pagesPath = KnowledgeStoragePath::forSource($sourceFile).'/pages.json';
        Storage::put($pagesPath, $pages->toJson());

        $sourceFile->update(['parser_filepath' => $pagesPath]);

        return $pages;
    }
}
