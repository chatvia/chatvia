<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Parsing;

use Domain\Knowledge\Document\Contracts\PdfInspector;
use Domain\Knowledge\Document\Conversion\ReaderImage;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use Domain\Knowledge\Ingestion\Services\Clients\AzureDocumentIntelligence;
use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Document parser backed by Azure AI Document Intelligence: reads PDFs, Word,
 * slides and images with their tables ({@see DocumentReaders::AZURE_READS}).
 * An image in a format or size Azure does not take (GIF, WebP, AVIF; under
 * 50 or over 10,000 pixels a side) is sent as a fitted PNG. Excel, CSV, old
 * office formats and RTF are read by the app: Azure does not read them, or
 * (Excel) loses their tables.
 *
 * Azure's free tier (F0) reads only the first 2 pages of a file, without an
 * error. A PDF that comes back with 2 of more pages fails with that reason,
 * rather than training part of the document.
 */
class AzureDocumentParser implements DocumentParser
{
    /** The shortest and longest image side Azure takes, in pixels. */
    private const int MIN_SIDE = 50;

    private const int MAX_SIDE = 10_000;

    /** The pages the free tier reads of each file. */
    private const int FREE_TIER_PAGES = 2;

    public function __construct(
        private readonly AzureDocumentIntelligence $azure,
        private readonly LocalDocumentParser $local,
        private readonly ReaderImage $images,
        private readonly PdfInspector $pdfInspector,
    ) {}

    public function parse(string $filePath, string $storageDirectory): Collection
    {
        if (! File::isFile($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }

        $extension = Str::lower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (SourceFileTypes::isImage($filePath)) {
            return $this->analyzeImage($filePath, $extension);
        }

        if (! DocumentReaders::readsItself('azure', $extension)) {
            return $this->local->parse($filePath, $storageDirectory);
        }

        $pages = $this->azure->analyze($filePath);

        if ($extension === 'pdf') {
            $this->ensureAllPagesRead($filePath, $pages);
        }

        return $pages;
    }

    /** The layout model, such as "azure:prebuilt-layout". */
    public function name(): string
    {
        return 'azure:'.config('services.azure_document_intelligence.model');
    }

    /**
     * @return Collection<int, PageData>
     */
    private function analyzeImage(string $path, string $extension): Collection
    {
        if (DocumentReaders::readsItself('azure', $extension) && ! $this->images->outside($path, self::MIN_SIDE, self::MAX_SIDE)) {
            return $this->azure->analyze($path);
        }

        $png = $this->images->png($path, self::MIN_SIDE, self::MAX_SIDE);

        try {
            return $this->azure->analyze($png);
        } finally {
            File::delete($png);
        }
    }

    /**
     * @param  Collection<int, PageData>  $pages
     */
    private function ensureAllPagesRead(string $path, Collection $pages): void
    {
        try {
            $total = $this->pdfInspector->getInfo($path)->length;
        } catch (Throwable) {
            return;
        }

        if ($pages->count() === self::FREE_TIER_PAGES && $total > self::FREE_TIER_PAGES) {
            throw DocumentParsingException::failed(sprintf(
                'Azure read only the first %d of %d pages. Your Azure resource is on the free tier (F0), which reads %d pages of each file. Change it to the standard tier (S0) in the Azure portal, then train the file again.',
                self::FREE_TIER_PAGES,
                $total,
                self::FREE_TIER_PAGES,
            ));
        }
    }
}
