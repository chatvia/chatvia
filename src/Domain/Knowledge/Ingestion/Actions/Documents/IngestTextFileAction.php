<?php

namespace Domain\Knowledge\Ingestion\Actions\Documents;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Conversion\EbookText;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Ingest a text SourceFile by splitting its content directly with the
 * chunker, bypassing the document parser — the text is already chunk-ready,
 * so OCR would only add cost and latency. Handles raw text (.txt,
 * .md/.markdown) read as-is and structured-text ebooks (.epub, .fb2) read as
 * Markdown by {@see EbookText}; Markdown and ebooks are split as Markdown,
 * .txt files as plain text.
 *
 * Each part becomes one PageData and rides the same DocumentChunkIngestionJob
 * batch as the document path, so embedding, enhancement, progress and
 * completion work the same way.
 */
class IngestTextFileAction
{
    public function __construct(
        private readonly TextSplitter $splitter,
        private readonly EbookText $ebookText,
        private readonly DispatchSourceIngestionBatchAction $dispatchBatch,
    ) {}

    public function execute(SourceFile $sourceFile, Agent $agent, ?string $internalJobId = null): void
    {
        if ($sourceFile->isIngested()) {
            return;
        }

        $pages = collect(PageData::fromParts($this->splitter->split($this->text($sourceFile), $sourceFile->contentKind())));

        $sourceFile->update([
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => $this->splitter->name(),
        ]);

        $this->dispatchBatch->execute(
            $sourceFile,
            $agent,
            DocumentChunkIngestionJob::forPages($agent, $sourceFile, $pages),
        );
    }

    /**
     * Ebooks are structured text: they are read as Markdown so the splitter
     * has headings to work with. Raw text files are read as-is.
     */
    private function text(SourceFile $sourceFile): string
    {
        if (! $sourceFile->isEbook()) {
            return $sourceFile->readContents();
        }

        return $sourceFile->withLocalFile(
            fn (File $file): string => $this->ebookText->toMarkdown($file->getPathname(), $sourceFile->extension()),
        );
    }
}
