<?php

use Domain\Knowledge\Document\Conversion\PdfInfo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * PdfInfo turns `pdfinfo` text output into the PDF's page count. A file
 * without a readable page count must be rejected, never measured as zero pages.
 */
beforeEach(function (): void {
    $this->pdfPath = create_temp_dir().'/pdfinfo-test-'.Str::random().'.pdf';
    File::put($this->pdfPath, "%PDF-1.4\n%%EOF\n");
});

afterEach(function (): void {
    File::delete($this->pdfPath);
});

it('reads the page count and properties from the pdfinfo output', function (): void {
    Process::fake(['*' => Process::result("Title:          Handbook\nPages:          12\nPage size:      612 x 792 pts (letter)\n")]);

    $info = new PdfInfo()->getInfo($this->pdfPath);

    expect($info->length)->toBe(12)
        ->and($info->metadata['title'])->toBe('Handbook')
        ->and($info->metadata['page_size_width'])->toBe(612.0)
        ->and($info->metadata['page_size_height'])->toBe(792.0);
});

it('rejects a PDF whose page count pdfinfo does not report', function (): void {
    Process::fake(['*' => Process::result("Title:          Broken\n")]);

    new PdfInfo()->getInfo($this->pdfPath);
})->throws(RuntimeException::class, 'PDFInfo reported no page count.');
