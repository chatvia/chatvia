<?php

namespace Domain\Knowledge\Ingestion\Services\Parsing;

use Domain\Knowledge\Document\Conversion\OfficeText;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Document parser that runs on the server with no outside service. PDFs are
 * read with poppler's `pdftotext`; office and plain-text files are read by
 * {@see OfficeText} (its built-in readers and PhpSpreadsheet, with pandoc
 * when installed), which also gives their pages — the same count the upload
 * check uses.
 *
 * It only reads the text layer: it cannot read scanned pages or images, and it
 * does not pull out the images inside a document.
 */
class LocalDocumentParser implements DocumentParser
{
    /** pdftotext ends every page with a form feed. */
    private const string PAGE_BREAK = "\f";

    private const int TIMEOUT_SECONDS = 600;

    public function __construct(
        private readonly OfficeText $officeText,
    ) {}

    public function parse(string $filePath, string $storageDirectory): Collection
    {
        if (! is_file($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }

        if (SourceFileTypes::isImage($filePath)) {
            throw DocumentParsingException::imagesNeedOcr();
        }

        $extension = Str::lower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf' || is_pdf_file($filePath)) {
            return $this->pages($this->pdfText($filePath));
        }

        if (! $this->officeText->supports($extension)) {
            throw DocumentParsingException::unsupportedFormat($extension);
        }

        return $this->pageData($this->officeText->pages($filePath, $extension));
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
            $error = strtok(Str::trim($result->errorOutput()), "\n") ?: 'pdftotext exited with an error';

            throw DocumentParsingException::failed(Str::substr($error, 0, 200));
        }

        return $result->output();
    }

    /**
     * @return Collection<int, PageData>
     */
    private function pages(string $text): Collection
    {
        $pages = Str::of($text)->explode(self::PAGE_BREAK)->all();

        // The form feed after the last page leaves one empty entry at the end.
        if (count($pages) > 1 && Str::trim(end($pages)) === '') {
            array_pop($pages);
        }

        return $this->pageData($pages);
    }

    /**
     * @param  array<int, string>  $pages
     * @return Collection<int, PageData>
     */
    private function pageData(array $pages): Collection
    {
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
        $text = TextCleaner::clean(Str::replace(["\r\n", "\r"], "\n", $page));

        // Trailing spaces on each line, then at most one blank line in a row.
        return Str::of($text)
            ->replaceMatches('/[ \t]+$/m', '')
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->toString();
    }
}
