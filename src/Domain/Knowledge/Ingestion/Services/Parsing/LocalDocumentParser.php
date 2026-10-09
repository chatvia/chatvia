<?php

namespace Domain\Knowledge\Ingestion\Services\Parsing;

use Domain\Knowledge\Document\Conversion\LibreOffice;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

/**
 * Document parser that runs on the server with no outside service. PDFs are
 * read with poppler's `pdftotext`; office files are first turned into a PDF
 * with LibreOffice (the same tool the app uses to count their pages), so page
 * numbers match what the owner is billed for.
 *
 * It only reads the text layer: it cannot read scanned pages or images, and it
 * does not pull out the images and tables inside a document.
 */
class LocalDocumentParser implements DocumentParser
{
    /** pdftotext ends every page with a form feed. */
    private const string PAGE_BREAK = "\f";

    private const int TIMEOUT_SECONDS = 600;

    public function __construct(
        private readonly LibreOffice $libreOffice,
    ) {}

    public function parse(string $filePath, string $storageDirectory): Collection
    {
        if (! is_file($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }

        if (SourceFileTypes::isImage($filePath)) {
            throw DocumentParsingException::imagesNeedOcr();
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf' || is_pdf_file($filePath)) {
            return $this->pages($this->pdfText($filePath));
        }

        if (! $this->libreOffice->supportsFormat($extension)) {
            throw DocumentParsingException::unsupportedFormat($extension);
        }

        $pdfPath = $this->libreOffice->toPdf($filePath);

        try {
            return $this->pages($this->pdfText($pdfPath));
        } finally {
            @unlink($pdfPath);
        }
    }

    public function name(): string
    {
        return 'local';
    }

    private function pdfText(string $pdfPath): string
    {
        $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
            (string) config('services.bin.pdftotext'),
            '-enc', 'UTF-8',
            $pdfPath,
            '-', // write to stdout
        ]);

        if (! $result->successful()) {
            $error = strtok(trim($result->errorOutput()), "\n") ?: 'pdftotext exited with an error';

            throw DocumentParsingException::failed(mb_substr($error, 0, 200));
        }

        return $result->output();
    }

    /**
     * @return Collection<int, PageData>
     */
    private function pages(string $text): Collection
    {
        $pages = explode(self::PAGE_BREAK, $text);

        // The form feed after the last page leaves one empty entry at the end.
        if (count($pages) > 1 && trim((string) end($pages)) === '') {
            array_pop($pages);
        }

        return collect($pages)
            ->values()
            ->map(function (string $page, int $index): PageData {
                $content = $this->clean($page);

                return new PageData(
                    pageNumber: $index + 1,
                    content: $content,
                    checksum: hash('xxh128', $content),
                );
            });
    }

    private function clean(string $page): string
    {
        $text = TextCleaner::clean(str_replace(["\r\n", "\r"], "\n", $page));

        // Trailing spaces on each line, then at most one blank line in a row.
        $text = (string) preg_replace('/[ \t]+$/m', '', $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
