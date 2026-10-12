<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Spatie\LaravelPdf\Facades\Pdf;

/**
 * A PDF of an office or plain-text file's text, for the document AI
 * (summaries, chat with a file), which reads only PDFs. It carries the text,
 * not the original layout: each page from {@see OfficeText} becomes a PDF page,
 * laid out by WeasyPrint (config/laravel-pdf.php).
 */
class OfficePdf
{
    /** Plain text on A4; pre-wrap keeps the line breaks of tables and lists. */
    private const string STYLE = <<<'CSS'
        @page { size: A4; margin: 2cm; }
        body { font-family: "Noto Sans", "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.4; }
        section { white-space: pre-wrap; unicode-bidi: plaintext; page-break-after: always; }
        section:last-child { page-break-after: auto; }
        CSS;

    public function __construct(
        private readonly OfficeText $text,
    ) {}

    /**
     * Writes the PDF of a file's text to a temporary local file and returns
     * its path. The caller deletes the file.
     */
    public function toPdf(string $path, string $extension): string
    {
        return $this->fromPages($this->text->pages($path, $extension));
    }

    /**
     * Writes a PDF with one page of text per entry to a temporary local file
     * and returns its path. The caller deletes the file.
     *
     * @param  array<int, string>  $pages
     */
    public function fromPages(array $pages): string
    {
        $sections = array_map(
            fn (string $page): string => '<section>'.e($page).'</section>',
            $pages,
        );

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'.self::STYLE.'</style></head><body>'
            .implode('', $sections)
            .'</body></html>';

        $pdfPath = create_temp_file('office.pdf', '');

        Pdf::html($html)->save($pdfPath);

        return $pdfPath;
    }
}
