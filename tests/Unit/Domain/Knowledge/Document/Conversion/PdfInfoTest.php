<?php

declare(strict_types=1);

use Domain\Knowledge\Document\Conversion\PdfInfo;
use Illuminate\Support\Facades\Process;

/**
 * PdfInfo turns `pdfinfo` text output into the PDF's page count. A file
 * without a readable page count must be rejected, never measured as zero pages.
 */
it('rejects a PDF whose page count pdfinfo does not report', function (): void {
    Process::fake(['*' => Process::result("Title:          Broken\n")]);
    $path = tempTestFile('pdfinfo-test.pdf', "%PDF-1.4\n%%EOF\n");

    expect(fn () => new PdfInfo()->getInfo($path))->toThrow(RuntimeException::class, 'PDFInfo reported no page count.');
});
